<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * ============================================================================
 * AMANIA UNIFIED DIGITAL PLATFORM ASSISTANT
 * Arsitektur Hibrida Retrieval-Augmented Generation (Hybrid RAG) dengan 
 * Integrasi Data Transaksional Dinamis pada Chatbot Layanan Informasi 
 * Platform Layanan Digital Terpadu
 * ============================================================================
 * 
 * Sesuai judul paper penelitian:
 * "Arsitektur Hibrida Retrieval-Augmented Generation dengan Integrasi Data 
 *  Transaksional Dinamis pada Chatbot Layanan Informasi Platform Layanan Digital Terpadu"
 * 
 * Pilar Layanan Digital Terpadu Amania:
 * 1. Penyelenggaraan Webinar & Seminar Daring (Events / Digital Webinars)
 * 2. Modul Komersial E-Products Premium (E-Books, Templates, Bundles, Coding Assets)
 * 3. Kelas Pelatihan Kursus Online (Courses, Lessons, Exams, Sertifikasi Kompetensi)
 * 
 * Pipeline Arsitektur:
 * 1. Multi-Entity Extraction: Ekstraksi tiket (TK-...), invoice kursus/e-product (INV-...), kuota, dsb.
 * 2. Multi-Domain Knowledge Retrieval (RAG): Katalog seminar/webinar, modul e-product, kelas kursus, silabus, FAQ platform terpadu.
 * 3. Dynamic Transactional Data Engine: Integrasi data transaksional multi-layanan (tiket aktif, riwayat kursus, pesanan e-product, sisa kuota live).
 * 4. Hybrid Generation Engine dengan Human/CS Escalation:
 *    - Primary Engine: LLM Generative (Google Gemini 1.5/2.0 Flash) via SSE Streaming
 *    - Local Hybrid Fallback Engine: Zero-downtime semantic matcher dari MySQL
 *    - Fallback Policy: Jika query terlalu kompleks, sengketa transaksi, atau di luar domain, eskalasikan langsung ke Customer Service resmi.
 */
class EventAssistantController extends Controller
{
    private const CS_PHONE = '+62 898-5477-864';
    private const CS_WA_URL = 'https://wa.me/628985477864?text=Halo%20Admin%20Customer%20Service%20Amania,%20saya%20membutuhkan%20bantuan%20terkait%20platform%20Amania.';

    public function chat(Request $request)
    {
        $request->validate([
            'message'    => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:100',
            'history'    => 'nullable|array|max:10',
        ]);

        $message = trim($request->input('message'));

        // 1. Resolve User jika request membawa Bearer Token
        $user = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $rawToken = substr($authHeader, 7);
            $tokenModel = PersonalAccessToken::findToken($rawToken);
            if ($tokenModel) {
                $user = $tokenModel->tokenable;
            }
        }

        try {
            // 2. Ekstraksi Entitas Transaksional Dinamis (Ticket Code / Invoice Reference)
            $queriedData = $this->extractQueriedTransaction($message);

            // 3. Bangun Konteks Multi-Domain Knowledge RAG & Data Transaksional
            $ragContext = $this->buildKnowledgeContext();
            $transactionalContext = $this->buildTransactionalContext($user, $queriedData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('EventAssistant context build warning: ' . $e->getMessage());
            $queriedData = ['ticket' => null, 'course_enrollment' => null, 'eproduct_purchase' => null];
            $ragContext = '';
            $transactionalContext = '';
        }

        // 4. Periksa Ketersediaan Google Gemini API
        $geminiApiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        $model = config('services.gemini.model') ?? env('GEMINI_MODEL', 'gemini-2.5-flash');

        if ($geminiApiKey) {
            $systemPrompt = $this->buildSystemPrompt($ragContext, $transactionalContext, $user, $queriedData);
            
            $history = $request->input('history', []);
            $contents = [];
            foreach (array_slice($history, -8) as $msg) {
                $role = ($msg['role'] ?? 'user') === 'user' ? 'user' : 'model';
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $msg['content'] ?? '']],
                ];
            }
            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => $message]],
            ];

            $payload = [
                'system_instruction' => [
                    'parts' => ['text' => $systemPrompt],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'topP'            => 0.9,
                    'maxOutputTokens' => 1200,
                ],
            ];

            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':streamGenerateContent?alt=sse&key=' . $geminiApiKey;

            $testCh = curl_init($url);
            curl_setopt($testCh, CURLOPT_POST, 1);
            curl_setopt($testCh, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($testCh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($testCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($testCh, CURLOPT_TIMEOUT, 30);
            $testResponse = curl_exec($testCh);
            $httpCode = curl_getinfo($testCh, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($testCh);
            curl_close($testCh);
            if ($httpCode !== 200) {
                \Illuminate\Support\Facades\Log::warning("Gemini API returned HTTP $httpCode (Error: $curlErr). Response: " . substr($testResponse, 0, 500));
            }

            if ($httpCode === 200 && $testResponse) {
                return response()->stream(function () use ($testResponse) {
                    echo ":" . str_repeat(" ", 2048) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();

                    $lines = explode("\n", $testResponse);
                    foreach ($lines as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $jsonStr = substr($line, 6);
                            $json = json_decode($jsonStr, true);
                            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                                $chunkText = $json['candidates'][0]['content']['parts'][0]['text'];
                                echo "data: " . json_encode(['chunk' => $chunkText]) . "\n\n";
                                if (ob_get_level() > 0) ob_flush();
                                flush();
                            }
                        }
                    }
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }, 200, [
                    'Cache-Control'     => 'no-cache',
                    'Content-Type'      => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Connection'        => 'keep-alive',
                ]);
            }
        }

        // 5. Fallback ke Local Hybrid RAG Engine (Zero-downtime, realtime database, dengan fallback CS)
        return $this->streamLocalHybridRagResponse($message, $user, $queriedData);
    }

    /**
     * Ekstraksi kode tiket event atau nomor invoice transaksi dari query pengguna
     */
    private function extractQueriedTransaction(string $message): array
    {
        $result = [
            'ticket' => null,
            'course_enrollment' => null,
            'eproduct_purchase' => null,
        ];

        // 1. Deteksi Ticket Code (misal TK-NATION-007)
        if (preg_match('/TK-[A-Z0-9-]+/i', $message, $m)) {
            $ticketCode = strtoupper($m[0]);
            $result['ticket'] = Registration::with('event')->where('ticket_code', $ticketCode)->first();
        }

        // 2. Deteksi Invoice Kursus (INV-CRS-...)
        if (preg_match('/INV-CRS-[A-Z0-9-]+/i', $message, $m)) {
            $ref = strtoupper($m[0]);
            $result['course_enrollment'] = CourseEnrollment::with('course', 'user')->where('reference', $ref)->first();
        }

        // 3. Deteksi Invoice E-Product (INV-EP-...)
        if (preg_match('/INV-EP-[A-Z0-9-]+/i', $message, $m)) {
            $ref = strtoupper($m[0]);
            $result['eproduct_purchase'] = EProductPurchase::with(['items.eProduct', 'buyer'])->where('reference', $ref)->first();
        }

        // 4. Deteksi Invoice umum jika diawali INV-
        if (preg_match('/INV-[A-Z0-9-]+/i', $message, $m)) {
            $ref = strtoupper($m[0]);
            if (!$result['course_enrollment']) {
                $result['course_enrollment'] = CourseEnrollment::with('course', 'user')->where('reference', $ref)->first();
            }
            if (!$result['eproduct_purchase']) {
                $result['eproduct_purchase'] = EProductPurchase::with(['items.eProduct', 'buyer'])->where('reference', $ref)->first();
            }
        }

        return $result;
    }

    /**
     * Local Hybrid RAG Engine
     * Mengimplementasikan Rule Intent Router dan Fallback Customer Service secara presisi
     */
    private function streamLocalHybridRagResponse(string $query, $user, array $queriedData)
    {
        $q = strtolower($query);
        $responseChunks = [];

        $hasTicketMatch = !empty($queriedData['ticket']);
        $hasCourseMatch = !empty($queriedData['course_enrollment']);
        $hasEproductMatch = !empty($queriedData['eproduct_purchase']);

        // ── INTENT 1: PANCARIAN TRANSAKSI / TIKET / INVOICE SPESIFIK ──
        if ($hasTicketMatch || $hasCourseMatch || $hasEproductMatch) {
            $responseChunks[] = "### 🔍 Hasil Pelacakan Data Transaksional Terpadu:\n\n";

            if ($hasTicketMatch) {
                $reg = $queriedData['ticket'];
                $ev = $reg->event;
                $statusLabel = match ($reg->status) {
                    'verified' => '✅ TERVERIFIKASI (Tiket Aktif)',
                    'pending'  => '⏳ MENUNGGU VERIFIKASI ADMIN',
                    'rejected' => '❌ DITOLAK (Perlu Upload Ulang Bukti)',
                    default    => strtoupper($reg->status),
                };
                $badgeColor = $reg->status === 'verified' ? 'green' : ($reg->status === 'pending' ? 'yellow' : 'red');

                $responseChunks[] = "🎟️ **Layanan Webinar / Acara Digital**:\n";
                $responseChunks[] = "- **Kode Tiket**: `{$reg->ticket_code}`\n";
                $responseChunks[] = "- **Nama Acara**: " . ($ev ? $ev->title : 'Webinar Amania') . "\n";
                $responseChunks[] = "- **Nama Peserta**: {$reg->name} ({$reg->email})\n";
                $responseChunks[] = "- **Tier Tiket**: " . strtoupper($reg->tier ?? 'free') . "\n";
                $responseChunks[] = "- **Status Saat Ini**: **{$statusLabel}**\n";

                if ($reg->status === 'verified' && $ev && $ev->join_link) {
                    $responseChunks[] = "- **Link Akses Acara**: [Masuk ke Sesi Live]({$ev->join_link})\n";
                } elseif ($reg->status === 'rejected') {
                    $responseChunks[] = "- **Alasan Penolakan**: " . ($reg->rejection_reason ?: 'Bukti transfer tidak jelas/buram.') . "\n";
                    $responseChunks[] = "  *Silakan upload ulang bukti pembayaran di menu Tiket Aktif atau hubungi Customer Service jika ada kendala.*\n";
                }

                $card = json_encode([
                    'type'     => 'ticket',
                    'title'    => $reg->ticket_code,
                    'subtitle' => $ev ? $ev->title : 'Tiket Acara',
                    'badge'    => ['label' => strtoupper($reg->status), 'color' => $badgeColor],
                    'detail'   => 'Status: ' . $statusLabel,
                    'link'     => '/events',
                    'icon'     => $reg->status === 'verified' ? 'check' : ($reg->status === 'pending' ? 'clock' : 'alert'),
                ]);
                $responseChunks[] = "\n[CARD:{$card}]\n\n";
            }

            if ($hasCourseMatch) {
                $enr = $queriedData['course_enrollment'];
                $crs = $enr->course;
                $statusLabel = match ($enr->status) {
                    'PAID'     => '✅ LUNAS (Akses Materi Terbuka)',
                    'UNPAID'   => '⏳ BELUM DIBAYAR (Menunggu Pembayaran)',
                    'EXPIRED'  => '⚠️ KADALUARSA',
                    default    => strtoupper($enr->status),
                };
                $color = $enr->status === 'PAID' ? 'green' : 'yellow';

                $responseChunks[] = "🎓 **Layanan Kursus Online (E-Learning)**:\n";
                $responseChunks[] = "- **No. Invoice**: `{$enr->reference}`\n";
                $responseChunks[] = "- **Kursus**: " . ($crs ? $crs->title : 'Kursus Amania') . "\n";
                $responseChunks[] = "- **Total Biaya**: Rp " . number_format($enr->amount, 0, ',', '.') . "\n";
                $responseChunks[] = "- **Status Pembayaran**: **{$statusLabel}**\n";

                $card = json_encode([
                    'type'     => 'course',
                    'title'    => $enr->reference,
                    'subtitle' => $crs ? $crs->title : 'Kursus',
                    'badge'    => ['label' => strtoupper($enr->status), 'color' => $color],
                    'detail'   => 'Kelas: ' . ($crs ? $crs->title : '-'),
                    'link'     => $crs ? "/courses/{$crs->slug}" : '/courses',
                    'icon'     => 'course',
                ]);
                $responseChunks[] = "\n[CARD:{$card}]\n\n";
            }

            if ($hasEproductMatch) {
                $pur = $queriedData['eproduct_purchase'];
                $statusLabel = match ($pur->status) {
                    'PAID'     => '✅ LUNAS (File Materi Siap Diunduh)',
                    'UNPAID'   => '⏳ BELUM DIBAYAR',
                    default    => strtoupper($pur->status),
                };
                $color = $pur->status === 'PAID' ? 'green' : 'yellow';

                $itemNames = $pur->items->map(fn($i) => $i->eProduct?->title ?? 'Modul E-Product')->implode(', ');

                $responseChunks[] = "📦 **Layanan Modul E-Product Premium**:\n";
                $responseChunks[] = "- **No. Invoice**: `{$pur->reference}`\n";
                $responseChunks[] = "- **Produk Digital**: {$itemNames}\n";
                $responseChunks[] = "- **Total Nominal**: Rp " . number_format($pur->amount, 0, ',', '.') . "\n";
                $responseChunks[] = "- **Status Transaksi**: **{$statusLabel}**\n";

                $card = json_encode([
                    'type'     => 'eproduct',
                    'title'    => $pur->reference,
                    'subtitle' => $itemNames,
                    'badge'    => ['label' => strtoupper($pur->status), 'color' => $color],
                    'detail'   => 'Status: ' . $statusLabel,
                    'link'     => '/e-products',
                    'icon'     => 'package',
                ]);
                $responseChunks[] = "\n[CARD:{$card}]\n\n";
            }
        }
        // ── INTENT 2: CEK STATUS TRANSAKSI / TIKET SAYA (USER LOGIN / GUEST) ──
        elseif (str_contains($q, 'tiket saya') || str_contains($q, 'transaksi saya') || str_contains($q, 'status tiket') || str_contains($q, 'status pendaftaran') || str_contains($q, 'pesanan saya')) {
            if ($user) {
                $responseChunks[] = "Halo **{$user->name}**! Berikut ringkasan **data transaksional terpadu** di akun Anda:\n\n";

                // 1. Registrasi Webinar
                $regs = Registration::where('user_id', $user->id)->with('event')->orderBy('created_at', 'desc')->get();
                if ($regs->isNotEmpty()) {
                    $responseChunks[] = "🎟️ **Webinar & Seminar Daring**:\n";
                    foreach ($regs as $r) {
                        $st = match ($r->status) {
                            'verified' => '✅ Aktif',
                            'pending'  => '⏳ Verifikasi',
                            'rejected' => '❌ Ditolak',
                            default    => $r->status,
                        };
                        $evTitle = $r->event ? $r->event->title : 'Event';
                        $responseChunks[] = "• **{$evTitle}**\n  Kode: `{$r->ticket_code}` | Tier: " . strtoupper($r->tier ?? 'free') . " | Status: **{$st}**\n";
                    }
                    $responseChunks[] = "\n";
                }

                // 2. Kursus Online
                $enrs = CourseEnrollment::where('user_id', $user->id)->with('course')->orderBy('created_at', 'desc')->get();
                if ($enrs->isNotEmpty()) {
                    $responseChunks[] = "🎓 **Kursus Online Pelatihan**:\n";
                    foreach ($enrs as $e) {
                        $st = $e->status === 'PAID' ? '✅ Terdaftar (Lunas)' : '⏳ Belum Dibayar';
                        $crTitle = $e->course ? $e->course->title : 'Kursus';
                        $responseChunks[] = "• **{$crTitle}**\n  Invoice: `{$e->reference}` | Status: **{$st}**\n";
                    }
                    $responseChunks[] = "\n";
                }

                // 3. E-Products
                $purs = EProductPurchase::where('user_id', $user->id)->with('items.eProduct')->orderBy('created_at', 'desc')->get();
                if ($purs->isNotEmpty()) {
                    $responseChunks[] = "📦 **Modul E-Products Premium**:\n";
                    foreach ($purs as $p) {
                        $st = $p->status === 'PAID' ? '✅ Lunas (Akses Buka)' : '⏳ Belum Dibayar';
                        $itemsStr = $p->items->map(fn($it) => $it->eProduct?->title ?? 'Modul')->implode(', ');
                        $responseChunks[] = "• **{$itemsStr}**\n  Invoice: `{$p->reference}` | Status: **{$st}**\n";
                    }
                    $responseChunks[] = "\n";
                }

                if ($regs->isEmpty() && $enrs->isEmpty() && $purs->isEmpty()) {
                    $responseChunks[] = "Anda belum memiliki riwayat transaksi di platform Amania.\nSilakan jelajahi [Katalog Event](/events), [Kursus Online](/courses), atau [E-Products](/e-products) untuk mulai beraktivitas!\n";
                }
            } else {
                $responseChunks[] = "Untuk memeriksa transaksi atau tiket Anda secara otomatis, silakan **[Masuk ke Akun Anda](/login)**.\n\n";
                $responseChunks[] = "💡 *Tips untuk Pengunjung Guest*: Anda juga dapat langsung mengetikkan kode tiket atau nomor invoice Anda di sini (contoh: `Cek tiket TK-NATION-007` atau `Cek invoice INV-EP-...`).\n";
            }
        }
        // ── INTENT 3: ESKALASI PERTANYAAN KOMPLEKS & FALLBACK CUSTOMER SERVICE ──
        elseif (
            str_contains($q, 'customer service') || str_contains($q, 'cs') || str_contains($q, 'admin') || str_contains($q, 'bantuan') ||
            str_contains($q, 'komplain') || str_contains($q, 'sengketa') || str_contains($q, 'salah transfer') || str_contains($q, 'gagal bayar') ||
            str_contains($q, 'lupa password') || str_contains($q, 'refund') || str_contains($q, 'pengembalian dana') ||
            str_contains($q, 'kerja sama') || str_contains($q, 'b2b') || str_contains($q, 'instruktur') ||
            str_contains($q, 'kendala') || str_contains($q, 'masalah') || str_contains($q, 'error')
        ) {
            $responseChunks[] = "### 💬 Layanan Eskalasi Bantuan Customer Service\n\n";
            $responseChunks[] = "Terima kasih atas pertanyaan Anda. Sejalan dengan batasan sistem layanan informasi otomatis kami, untuk pertanyaan yang lebih mendalam, kendala teknis akun spesifik, penyesuaian nominal transaksi, permohonan pengembalian dana (refund), maupun permintaan khusus yang memerlukan tindakan administratif manusia, silakan langsung menghubungi **Customer Service Resmi Platform Amania**.\n\n";
            $responseChunks[] = "Tim Customer Service kami siap melayani Anda secara langsung:\n";
            $responseChunks[] = "- 📱 **WhatsApp Resmi**: [" . self::CS_PHONE . "](" . self::CS_WA_URL . ")\n";
            $responseChunks[] = "- ⏰ **Jam Operasional**: Setiap hari (08.00 – 21.00 WIB)\n";
            $responseChunks[] = "- 🌐 **Halaman Pusat Bantuan**: [Pusat Bantuan Amania](/support)\n\n";
            $responseChunks[] = "Silakan klik tombol di bawah untuk langsung terhubung dengan tim Customer Service via WhatsApp:\n\n";

            $csCard = json_encode([
                'type'     => 'cs',
                'title'    => 'Hubungi Customer Service Amania',
                'subtitle' => 'Chat Langsung dengan Tim Human Support',
                'badge'    => ['label' => 'Bantuan Cepat', 'color' => 'green'],
                'detail'   => 'WhatsApp: ' . self::CS_PHONE,
                'link'     => self::CS_WA_URL,
                'icon'     => 'headset',
            ]);
            $responseChunks[] = "[CARD:{$csCard}]\n";
        }
        // ── INTENT 4: WEBINAR / EVENT & SISA KUOTA ──
        elseif (str_contains($q, 'webinar') || str_contains($q, 'event') || str_contains($q, 'seminar') || str_contains($q, 'acara') || str_contains($q, 'workshop') || str_contains($q, 'kuota') || str_contains($q, 'jadwal')) {
            $events = Event::orderBy('start_time', 'asc')->get();
            $responseChunks[] = "### 📅 Penyelenggaraan Webinar & Seminar Daring Amania:\n\n";
            $responseChunks[] = "Berikut jadwal kegiatan webinar terkini beserta **status sisa kuota kursi real-time**:\n\n";

            foreach ($events as $ev) {
                $regCount = Registration::where('event_id', $ev->id)->whereIn('status', ['verified', 'pending'])->count();
                $rem = max(0, ($ev->quota ?? 100) - $regCount);
                $priceStr = $ev->basic_price == 0 ? 'GRATIS' : 'Rp ' . number_format($ev->basic_price, 0, ',', '.');
                $statusQuota = $rem <= 10 ? "🔥 **Hampir Penuh!**" : "Tersedia";

                $responseChunks[] = "• **[{$ev->title}](/events/{$ev->slug})**\n";
                $responseChunks[] = "  - ⏰ Jadwal: " . ($ev->start_time ? $ev->start_time->format('d M Y, H:i') . ' WIB' : 'TBA') . "\n";
                $responseChunks[] = "  - 📍 Venue: {$ev->venue}\n";
                $responseChunks[] = "  - 💰 Biaya: {$priceStr} (Tier Premium: Rp " . number_format($ev->premium_price ?? 0, 0, ',', '.') . ")\n";
                $responseChunks[] = "  - 👥 Sisa Kuota: **{$rem} slot kursi** ({$statusQuota})\n\n";

                $card = json_encode([
                    'type'     => 'event',
                    'title'    => $ev->title,
                    'subtitle' => "Sisa Kuota: {$rem} kursi | Biaya: {$priceStr}",
                    'badge'    => ['label' => "{$rem} Slot", 'color' => $rem <= 10 ? 'red' : 'blue'],
                    'detail'   => $ev->venue,
                    'link'     => "/events/{$ev->slug}",
                    'icon'     => 'calendar',
                ]);
                $responseChunks[] = "[CARD:{$card}]\n";
            }
        }
        // ── INTENT 5: E-PRODUCTS PREMIUM (MODUL KOMERSIAL) ──
        elseif (str_contains($q, 'e-product') || str_contains($q, 'eproduct') || str_contains($q, 'e product') || str_contains($q, 'modul') || str_contains($q, 'ebook') || str_contains($q, 'e-book') || str_contains($q, 'e book') || str_contains($q, 'template') || str_contains($q, 'buku')) {
            $eproducts = EProduct::where('is_published', true)->with('category')->get();
            $responseChunks[] = "### 📦 Modul Komersial E-Products Premium Amania:\n\n";
            $responseChunks[] = "Produk digital siap pakai dengan sistem **sekali beli, akses selamanya (lifetime access)**:\n\n";

            foreach ($eproducts as $ep) {
                $priceStr = $ep->price == 0 ? 'GRATIS' : 'Rp ' . number_format($ep->price, 0, ',', '.');
                $catName = $ep->category?->name ?? 'Digital Assets';

                $responseChunks[] = "• **[{$ep->title}](/e-products/{$ep->slug})**\n";
                $responseChunks[] = "  - 📂 Kategori: {$catName}\n";
                $responseChunks[] = "  - 💵 Harga: **{$priceStr}**\n";
                $responseChunks[] = "  - 📝 Deskripsi: " . substr(strip_tags($ep->description), 0, 120) . "...\n\n";

                $card = json_encode([
                    'type'     => 'eproduct',
                    'title'    => $ep->title,
                    'subtitle' => "Kategori: {$catName}",
                    'badge'    => ['label' => $priceStr, 'color' => 'violet'],
                    'detail'   => 'Akses Selamanya',
                    'link'     => "/e-products/{$ep->slug}",
                    'icon'     => 'package',
                ]);
                $responseChunks[] = "[CARD:{$card}]\n";
            }
        }
        // ── INTENT 6: KURSUS ONLINE PELATIHAN DIGITAL ──
        elseif (str_contains($q, 'kursus') || str_contains($q, 'course') || str_contains($q, 'kelas') || str_contains($q, 'pelatihan') || str_contains($q, 'belajar') || str_contains($q, 'materi kelas') || str_contains($q, 'sertifikat kelulusan')) {
            $courses = Course::where('is_published', true)->with('category')->get();
            $responseChunks[] = "### 🎓 Kelas Pelatihan Kursus Online Amania:\n\n";
            $responseChunks[] = "Platform LMS terstruktur dengan video panduan materi, modul praktis, ujian evaluasi, dan **e-sertifikat resmi**:\n\n";

            foreach ($courses as $c) {
                $priceStr = $c->price == 0 ? 'GRATIS' : 'Rp ' . number_format($c->price, 0, ',', '.');
                $catName = $c->category?->name ?? 'Teknologi';

                $responseChunks[] = "• **[{$c->title}](/courses/{$c->slug})**\n";
                $responseChunks[] = "  - 🎯 Level: " . ucfirst($c->level ?? 'Semua Level') . " | Kategori: {$catName}\n";
                $responseChunks[] = "  - 💰 Biaya: **{$priceStr}**\n";
                $responseChunks[] = "  - 📝 Ringkasan: " . substr(strip_tags($c->description), 0, 120) . "...\n\n";

                $card = json_encode([
                    'type'     => 'course',
                    'title'    => $c->title,
                    'subtitle' => "Level: " . ucfirst($c->level ?? 'Semua') . " | {$catName}",
                    'badge'    => ['label' => $priceStr, 'color' => 'green'],
                    'detail'   => 'Bersertifikat Resmi',
                    'link'     => "/courses/{$c->slug}",
                    'icon'     => 'course',
                ]);
                $responseChunks[] = "[CARD:{$card}]\n";
            }
        }
        // ── INTENT 7: PANDUAN PENDAFTARAN & METODE PEMBAYARAN TERPADU ──
        elseif (str_contains($q, 'cara daftar') || str_contains($q, 'cara bayar') || str_contains($q, 'qris') || str_contains($q, 'transfer') || str_contains($q, 'pembayaran')) {
            $responseChunks[] = "### 💳 Panduan Transaksi & Pembayaran Platform Digital Terpadu:\n\n";
            $responseChunks[] = "Platform Amania mengintegrasikan seluruh alur pembayaran digital untuk ketiga layanan:\n\n";
            $responseChunks[] = "1. **Metode Pembayaran Tersedia**:\n";
            $responseChunks[] = "   - **QRIS Dinamis / Manual**: Mendukung seluruh aplikasi e-wallet (GoPay, OVO, Dana, ShopeePay) serta Mobile Banking (BCA, Mandiri, BRI, BNI, dll).\n";
            $responseChunks[] = "   - **Transfer Bank Manual**: Transfer rekening panitia dengan konfirmasi upload bukti transfer.\n\n";
            $responseChunks[] = "2. **Alur Transaksi Per Layanan**:\n";
            $responseChunks[] = "   - **Webinar**: Pilih acara -> Pilih tier (Free/Premium) -> Bayar -> Upload bukti -> Tiket & QR Code terbit dalam status `verified`.\n";
            $responseChunks[] = "   - **E-Product**: Checkout modul -> Bayar via QRIS/Tripay -> File modul langsung terbuka di menu Unduhan saya.\n";
            $responseChunks[] = "   - **Kursus Online**: Enroll kelas -> Selesaikan pembayaran -> Akses seluruh video lesson & modul langsung aktif.\n\n";
            $responseChunks[] = "3. **Waktu Verifikasi**: Sistem otomatis memverifikasi pembayaran gateway instan. Untuk transfer manual, admin memverifikasi maksimal 1x24 jam kerja.\n";
        }
        // ── INTENT 8: FALLBACK JIKA PERTANYAAN KOMPLEKS ATAU TIDAK DIKENALI ──
        elseif (strlen($q) > 60 || str_contains($q, 'kenapa') || str_contains($q, 'mengapa') || str_contains($q, 'bagaimana')) {
            $responseChunks[] = "### 💬 Layanan Bantuan Customer Service\n\n";
            $responseChunks[] = "Maaf, sistem asisten AI otomatis belum memiliki informasi yang cukup detail mengenai hal tersebut, atau pertanyaan Anda membutuhkan penanganan khusus dari pihak pengelola platform.\n\n";
            $responseChunks[] = "Untuk mendapatkan bantuan secara langsung, silakan hubungi **Customer Service Amania**:\n";
            $responseChunks[] = "- 📱 **WhatsApp Resmi**: [" . self::CS_PHONE . "](" . self::CS_WA_URL . ")\n";
            $responseChunks[] = "- ⏰ **Jam Layanan**: 08.00 – 21.00 WIB\n";
            $responseChunks[] = "- 🌐 **Pusat Bantuan**: [Halaman Support](/support)\n\n";

            $csCard = json_encode([
                'type'     => 'cs',
                'title'    => 'Hubungi Customer Service Amania',
                'subtitle' => 'Bantuan Langsung Tim Support',
                'badge'    => ['label' => 'CS Online', 'color' => 'green'],
                'detail'   => 'WhatsApp: ' . self::CS_PHONE,
                'link'     => self::CS_WA_URL,
                'icon'     => 'headset',
            ]);
            $responseChunks[] = "[CARD:{$csCard}]\n";
        }
        // ── DEFAULT: OVERVIEW LAYANAN DIGITAL TERPADU ──
        else {
            $responseChunks[] = "Halo! Saya **Amania Digital Assistant**, asisten AI cerdas berarsitektur **Hibrida RAG (Retrieval-Augmented Generation) + Integrasi Data Transaksional Dinamis** pada **Platform Layanan Digital Terpadu Amania**.\n\n";
            $responseChunks[] = "Platform kami mengintegrasikan 3 layanan digital utama dalam satu akun:\n\n";
            $responseChunks[] = "1. 📅 **[Webinar & Seminar Daring](/events)**: Jadwal seminar interaktif, sisa kuota kursi real-time, dan e-tiket bersertifikat.\n";
            $responseChunks[] = "2. 📦 **[Modul E-Products Premium](/e-products)**: E-book panduan, source code template, dan modul digital berlisensi akses selamanya.\n";
            $responseChunks[] = "3. 🎓 **[Kelas Pelatihan Kursus Online](/courses)**: Video materi kurikulum terstruktur, evaluasi ujian kuis, dan sertifikasi kelulusan resmi.\n\n";
            $responseChunks[] = "💡 *Apa yang ingin Anda ketahui? Anda bisa menanyakan info webinar, rekomendasi kursus, katalog modul e-product, atau mengetikkan kode tiket (`TK-...`) / no. invoice (`INV-...`) untuk melacak status transaksi secara langsung.*\n";
        }

        return response()->stream(function () use ($responseChunks) {
            echo ":" . str_repeat(" ", 2048) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            foreach ($responseChunks as $chunk) {
                echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                usleep(20000);
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'Content-Type'      => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // JALUR 1: MULTI-DOMAIN KNOWLEDGE RAG CONTEXT
    // ─────────────────────────────────────────────────────────
    private function buildKnowledgeContext(): string
    {
        $ctx = "=== PROFIL PLATFORM LAYANAN DIGITAL TERPADU AMANIA ===\n";
        $ctx .= "Amania adalah platform edukasi & ekosistem layanan digital terpadu yang menyatukan 3 pilar layanan:\n";
        $ctx .= "1. Penyelenggaraan Webinar & Seminar Daring (Events)\n";
        $ctx .= "2. Modul Komersial E-Products Premium (E-Books, Templates, Assets)\n";
        $ctx .= "3. Kelas Pelatihan Kursus Online Bersertifikat (Courses & LMS)\n";
        $ctx .= "Platform ini dilengkapi dengan gateway pembayaran multi-metode (QRIS Dinamis & Transfer Bank Manual) dan sistem akun tunggal terintegrasi.\n\n";

        // 1. Katalog Event
        $events = Event::orderBy('start_time', 'asc')->get();
        if ($events->isNotEmpty()) {
            $ctx .= "=== PILAR 1: KATALOG WEBINAR & SEMINAR DARING ===\n";
            foreach ($events as $event) {
                $now = now();
                $start = $event->start_time;
                $registeredCount = Registration::where('event_id', $event->id)
                    ->whereIn('status', ['verified', 'pending'])
                    ->count();
                $quotaRemaining = max(0, ($event->quota ?? 999) - $registeredCount);

                $status = ($start && $start->gt($now)) ? 'Akan Datang' : 'Sedang/Selesai';
                $ctx .= "- Judul: {$event->title}\n";
                $ctx .= "  Slug/Link: /events/{$event->slug}\n";
                $ctx .= "  Status: {$status}\n";
                $ctx .= "  Waktu: " . ($start ? $start->format('d M Y, H:i') . ' WIB' : 'TBA') . "\n";
                $ctx .= "  Venue: " . ($event->venue ?: 'Online via Zoom/Meet') . "\n";
                $ctx .= "  Harga Tiket: " . ($event->basic_price == 0 ? 'GRATIS' : 'Rp ' . number_format($event->basic_price, 0, ',', '.')) . " (Premium: Rp " . number_format($event->premium_price ?? 0, 0, ',', '.') . ")\n";
                $ctx .= "  Sisa Kuota Kursi Realtime: {$quotaRemaining} slot (dari total {$event->quota})\n";
                if ($event->description) {
                    $ctx .= "  Deskripsi: " . substr(strip_tags($event->description), 0, 200) . "...\n";
                }
                $ctx .= "\n";
            }
        }

        // 2. Katalog E-Products
        $eproducts = EProduct::where('is_published', true)->with('category')->get();
        if ($eproducts->isNotEmpty()) {
            $ctx .= "=== PILAR 2: KATALOG MODUL E-PRODUCTS PREMIUM ===\n";
            foreach ($eproducts as $ep) {
                $price = $ep->price == 0 ? 'GRATIS' : 'Rp ' . number_format($ep->price, 0, ',', '.');
                $cat = $ep->category?->name ?? 'Modul Digital';
                $ctx .= "- Produk: {$ep->title} (Kategori: {$cat})\n";
                $ctx .= "  Link: /e-products/{$ep->slug}\n";
                $ctx .= "  Harga: {$price}\n";
                $ctx .= "  Deskripsi: " . substr(strip_tags($ep->description), 0, 150) . "...\n";
            }
            $ctx .= "\n";
        }

        // 3. Katalog Kursus Online
        $courses = Course::where('is_published', true)->with('category')->get();
        if ($courses->isNotEmpty()) {
            $ctx .= "=== PILAR 3: KATALOG KURSUS ONLINE PELATIHAN ===\n";
            foreach ($courses as $c) {
                $price = $c->price == 0 ? 'GRATIS' : 'Rp ' . number_format($c->price, 0, ',', '.');
                $cat = $c->category?->name ?? 'Pelatihan';
                $ctx .= "- Kursus: {$c->title} (Level: " . ucfirst($c->level ?? 'Semua') . " | {$cat})\n";
                $ctx .= "  Link: /courses/{$c->slug}\n";
                $ctx .= "  Biaya: {$price} (Termasuk E-Sertifikat Resmi)\n";
                $ctx .= "  Ringkasan: " . substr(strip_tags($c->description), 0, 150) . "...\n";
            }
            $ctx .= "\n";
        }

        // 4. Prosedur Customer Service & Fallback
        $ctx .= "=== KEBIJAKAN LAYANAN & CUSTOMER SERVICE RESMI ===\n";
        $ctx .= "- Kontak WhatsApp Customer Service: " . self::CS_PHONE . " (Tautan: " . self::CS_WA_URL . ")\n";
        $ctx .= "- Jam Operasional Bantuan: Senin - Minggu (08.00 - 21.00 WIB)\n";
        $ctx .= "- Alur Pembayaran: Mendukung QRIS (OVO, GoPay, Dana, M-Banking) dan Transfer Bank Manual.\n";
        $ctx .= "- Verifikasi Pembayaran: Verifikasi otomatis untuk gateway Tripay, dan maksimal 1x24 jam untuk transfer manual.\n";
        $ctx .= "- Refund: Diperbolehkan untuk Webinar maksimal H-3. Pengajuan kasus sengketa transaksi khusus diarahkan langsung ke Customer Service.\n";
        $ctx .= "- ATURAN FALLBACK CS: Jika pertanyaan pengguna terlalu kompleks, mengenai sengketa uang, keluhan akun sensitif, atau tidak ada dalam data di atas, chatbot WAJIB menyarankan pengguna menghubungi Customer Service Amania via WhatsApp.\n\n";

        return $ctx;
    }

    // ─────────────────────────────────────────────────────────
    // JALUR 2: MULTI-DOMAIN DYNAMIC TRANSACTIONAL CONTEXT
    // ─────────────────────────────────────────────────────────
    private function buildTransactionalContext($user, array $queriedData): string
    {
        $ctx = '';

        // 1. Data Hasil Query Spesifik (Tiket atau Invoice)
        if (!empty($queriedData['ticket'])) {
            $reg = $queriedData['ticket'];
            $ev = $reg->event;
            $ctx .= "=== DATA TRANSAKSI TIKET SPESIFIK (HASIL QUERY USER) ===\n";
            $ctx .= "Kode Tiket: {$reg->ticket_code}\n";
            $ctx .= "Peserta: {$reg->name} ({$reg->email})\n";
            $ctx .= "Acara: " . ($ev ? $ev->title : 'Webinar') . "\n";
            $ctx .= "Status Tiket: {$reg->status}\n";
            $ctx .= "Tier: " . strtoupper($reg->tier ?? 'free') . "\n";
            if ($reg->rejection_reason) {
                $ctx .= "Alasan Penolakan: {$reg->rejection_reason}\n";
            }
            $ctx .= "\n";
        }

        if (!empty($queriedData['course_enrollment'])) {
            $enr = $queriedData['course_enrollment'];
            $crs = $enr->course;
            $ctx .= "=== DATA TRANSAKSI KURSUS SPESIFIK (HASIL QUERY USER) ===\n";
            $ctx .= "No. Invoice: {$enr->reference}\n";
            $ctx .= "Kursus: " . ($crs ? $crs->title : 'N/A') . "\n";
            $ctx .= "Total: Rp " . number_format($enr->amount, 0, ',', '.') . "\n";
            $ctx .= "Status: {$enr->status}\n\n";
        }

        if (!empty($queriedData['eproduct_purchase'])) {
            $pur = $queriedData['eproduct_purchase'];
            $itemsStr = $pur->items->map(fn($it) => $it->eProduct?->title ?? 'Modul')->implode(', ');
            $ctx .= "=== DATA TRANSAKSI E-PRODUCT SPESIFIK (HASIL QUERY USER) ===\n";
            $ctx .= "No. Invoice: {$pur->reference}\n";
            $ctx .= "Produk: {$itemsStr}\n";
            $ctx .= "Total: Rp " . number_format($pur->amount, 0, ',', '.') . "\n";
            $ctx .= "Status: {$pur->status}\n\n";
        }

        // 2. Data Transaksional Pengguna Aktif (User Login)
        if ($user) {
            $ctx .= "=== DATA TRANSAKSIONAL MULTI-LAYANAN USER AKTIF ===\n";
            $ctx .= "User: {$user->name} | Email: {$user->email}\n";

            // Tiket Event
            $regs = Registration::where('user_id', $user->id)->with('event')->get();
            if ($regs->isNotEmpty()) {
                $ctx .= "Daftar Tiket Webinar User:\n";
                foreach ($regs as $r) {
                    $evTitle = $r->event ? $r->event->title : 'Webinar';
                    $ctx .= "- Tiket: {$r->ticket_code} | Acara: {$evTitle} | Status: {$r->status} | Tier: {$r->tier}\n";
                }
            }

            // Kursus
            $enrollments = CourseEnrollment::where('user_id', $user->id)->with('course')->get();
            if ($enrollments->isNotEmpty()) {
                $ctx .= "Daftar Enrollment Kursus User:\n";
                foreach ($enrollments as $e) {
                    $crTitle = $e->course ? $e->course->title : 'Kursus';
                    $ctx .= "- Ref: {$e->reference} | Kursus: {$crTitle} | Status: {$e->status}\n";
                }
            }

            // E-Product
            $purchases = EProductPurchase::where('user_id', $user->id)->with('items.eProduct')->get();
            if ($purchases->isNotEmpty()) {
                $ctx .= "Daftar Pembelian E-Product User:\n";
                foreach ($purchases as $p) {
                    $itemsStr = $p->items->map(fn($it) => $it->eProduct?->title ?? 'Modul')->implode(', ');
                    $ctx .= "- Invoice: {$p->reference} | Produk: {$itemsStr} | Status: {$p->status}\n";
                }
            }
            $ctx .= "\n";
        }

        return $ctx;
    }

    // ─────────────────────────────────────────────────────────
    // SYSTEM PROMPT BUILDER (HYBRID RAG + CS ESCALATION)
    // ─────────────────────────────────────────────────────────
    private function buildSystemPrompt(string $ragContext, string $transactionalContext, $user, array $queriedData): string
    {
        $prompt = "Anda adalah **Amania Unified Digital Assistant**, chatbot AI layanan informasi cerdas yang dibangun di atas arsitektur **Hibrida Retrieval-Augmented Generation (Hybrid RAG) dengan Integrasi Data Transaksional Dinamis pada Platform Layanan Digital Terpadu Amania**.\n\n";

        $prompt .= "**RUANG LINGKUP PENGETAHUAN & LAYANAN TERPADU:**\n";
        $prompt .= "Anda melayani 3 pilar layanan digital terpadu:\n";
        $prompt .= "1. Penyelenggaraan Webinar & Seminar Daring (info jadwal, venue, kuota sisa live, pendaftaran, dan e-tiket)\n";
        $prompt .= "2. Modul Komersial E-Products Premium (katalog ebook, template, modul digital, lisensi akses seumur hidup)\n";
        $prompt .= "3. Kelas Pelatihan Kursus Online (silabus kursus, kurikulum LMS, instruktur, dan sertifikat kelulusan)\n";
        $prompt .= "4. Data Transaksional Terpadu Real-Time (pelacakan tiket pendaftaran, invoice e-product, dan status enrollment kursus)\n\n";

        $prompt .= "**ATURAN KRUSIAL PENANGANAN PERTANYAAN KOMPLEKS & FALLBACK KE CUSTOMER SERVICE:**\n";
        $prompt .= "- Apabila pengguna menanyakan hal yang lebih kompleks, di luar data platform Amania, sengketa transaksi/refund khusus, kendala teknis akun mendalam, atau hal yang tidak dapat dijawab secara pasti oleh sistem AI:\n";
        $prompt .= "  -> **JANGAN MENGARANG ATAU BERHALUSINASI!**\n";
        $prompt .= "  -> Berikan jawaban fallback yang ramah dan jujur bahwa pertanyaan tersebut membutuhkan penanganan langsung oleh tim manusia / Customer Service Amania.\n";
        $prompt .= "  -> Sertakan informasi kontak Customer Service WhatsApp resmi: [" . self::CS_PHONE . "](" . self::CS_WA_URL . ") dan link [Pusat Bantuan](/support).\n";
        $prompt .= "  -> Di akhir jawaban fallback, sertakan Rich Card format khusus Customer Service: [CARD:{\"type\":\"cs\",\"title\":\"Customer Service Amania\",\"subtitle\":\"Bantuan langsung oleh Tim Support\",\"badge\":{\"label\":\"CS Online\",\"color\":\"green\"},\"detail\":\"WhatsApp: " . self::CS_PHONE . "\",\"link\":\"" . self::CS_WA_URL . "\",\"icon\":\"headset\"}]\n\n";

        $prompt .= "**FORMAT RESPON:**\n";
        $prompt .= "- Bahasa Indonesia ramah, profesional, ringkas, dan jelas.\n";
        $prompt .= "- Gunakan Markdown untuk struktur teks.\n";
        $prompt .= "- Gunakan format link internal seperti [Katalog Webinar](/events), [Katalog Kursus](/courses), [E-Products](/e-products).\n\n";

        $prompt .= "## BASIS PENGETAHUAN MULTI-DOMAIN (Knowledge RAG):\n" . $ragContext . "\n\n";

        if ($transactionalContext) {
            $prompt .= "## DATA TRANSAKSIONAL DINAMIS TERINTEGRASI:\n" . $transactionalContext . "\n\n";
        }

        return $prompt;
    }
}
