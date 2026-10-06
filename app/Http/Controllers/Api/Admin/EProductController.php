<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Helpers\ImageHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EProductController extends Controller
{
    /**
     * TAMPILKAN SEMUA E-PRODUK
     */
    public function index(Request $request)
    {
        $query = EProduct::with(['author', 'category'])->latest();
        
        if ($request->user()->role === 'creator') {
            $query->where('user_id', $request->user()->id);
        }

        $products = $query->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * TAMPILKAN SATU E-PRODUK (Untuk Edit)
     */
    public function show(Request $request, $id)
    {
        // 🔥 PERBAIKAN: Tambahkan relasi 'materials' agar bisa ditampilkan di Frontend
        $product = EProduct::with(['category', 'materials'])->findOrFail($id);

        if ($request->user()->role === 'creator' && $product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * TAMBAH E-PRODUK BARU
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'e_product_category_id' => 'required|exists:e_product_categories,id', // 🔥 WAJIB ADA KATEGORI
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            // 🔥 VALIDASI FILE_UPLOAD & FILE_LINK SUDAH DIHAPUS DARI SINI 🔥
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'e_product_category_id', 'description', 'price', 'is_published']);
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['user_id'] = $request->user()->id; 

        // Upload Cover
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = ImageHelper::compressAndStore($request->file('cover_image'), 'e_products/covers', 1200, 900, 80);
        }

        // 🔥 LOGIKA UNTUK MENYIMPAN FILE_PATH SUDAH DIHAPUS 🔥

        $product = EProduct::create($data);

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil ditambahkan!',
            'data' => $product
        ], 201);
    }

    /**
     * UPDATE E-PRODUK
     */
    public function update(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);

        if ($request->user()->role === 'creator' && $product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'e_product_category_id' => 'required|exists:e_product_categories,id', // 🔥 WAJIB ADA KATEGORI
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            // 🔥 VALIDASI FILE_UPLOAD & FILE_LINK SUDAH DIHAPUS DARI SINI 🔥
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'e_product_category_id', 'description', 'price', 'is_published']);
        
        if ($request->title !== $product->title) {
            $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        }

        // Update Cover
        if ($request->hasFile('cover_image')) {
            // Hapus cover lama jika bukan link eksternal
            if ($product->cover_image && !Str::startsWith($product->cover_image, ['http://', 'https://']) && Storage::disk('public')->exists($product->cover_image)) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $data['cover_image'] = ImageHelper::compressAndStore($request->file('cover_image'), 'e_products/covers', 1200, 900, 80);
        }

        // 🔥 LOGIKA UNTUK MENGUPDATE FILE_PATH SUDAH DIHAPUS 🔥

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil diperbarui!',
            'data' => $product
        ]);
    }

    /**
     * HAPUS E-PRODUK
     */
    public function destroy(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);

        if ($request->user()->role === 'creator' && $product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Hapus file cover fisik
        if ($product->cover_image && !Str::startsWith($product->cover_image, ['http://', 'https://']) && Storage::disk('public')->exists($product->cover_image)) {
            Storage::disk('public')->delete($product->cover_image);
        }
        
        // 🔥 HAPUS FILE FISIK DARI MATERI (ZIP/PDF) SEBELUM MENGHAPUS PRODUK 🔥
        foreach ($product->materials as $mat) {
            if ($mat->type === 'file' && $mat->file_path && Storage::disk('public')->exists($mat->file_path)) {
                Storage::disk('public')->delete($mat->file_path);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'E-Produk dan materinya berhasil dihapus!'
        ]);
    }

    /**
     * MENDAPATKAN STATISTIK TRACKING BROADCAST & GENERATE LOG JIKA BELUM ADA
     */
    public function broadcastStats(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);

        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Hanya Superadmin yang diizinkan melakukan broadcast.'], 403);
        }

        $totalUsers = \App\Models\User::where('role', 'user')->count();
        if ($totalUsers === 0) {
            return response()->json(['success' => false, 'message' => 'Tidak ada konsumen untuk di-broadcast.']);
        }

        // Cek apakah log sudah ada untuk produk ini
        $logCount = \App\Models\EproductBroadcastLog::where('e_product_id', $id)->count();

        // Jika belum ada log sama sekali, kita generate log untuk SEMUA user konsumen
        if ($logCount === 0) {
            \App\Models\User::where('role', 'user')->chunk(500, function ($users) use ($id) {
                $logs = [];
                foreach ($users as $user) {
                    $logs[] = [
                        'e_product_id' => $id,
                        'user_id' => $user->id,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                \App\Models\EproductBroadcastLog::insert($logs);
            });
        }

        // Hitung statistik
        $total = \App\Models\EproductBroadcastLog::where('e_product_id', $id)->count();
        $sent = \App\Models\EproductBroadcastLog::where('e_product_id', $id)->where('status', 'sent')->count();
        $pending = \App\Models\EproductBroadcastLog::where('e_product_id', $id)->where('status', 'pending')->count();
        $failed = \App\Models\EproductBroadcastLog::where('e_product_id', $id)->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'sent' => $sent,
                'pending' => $pending,
                'failed' => $failed,
                'is_completed' => ($pending === 0 && $failed === 0)
            ]
        ]);
    }

    /**
     * MELANJUTKAN (RESUME) PENGIRIMAN BROADCAST DENGAN LIMIT (BATCHING)
     */
    public function broadcastSend(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);
        
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Hanya Superadmin yang diizinkan melakukan broadcast.'], 403);
        }

        $limit = $request->input('limit', 100); // Default 100 per request
        
        // Ambil log yang masih pending
        $pendingLogs = \App\Models\EproductBroadcastLog::with('user')
            ->where('e_product_id', $id)
            ->where('status', 'pending')
            ->limit($limit)
            ->get();

        if ($pendingLogs->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada email pending untuk dikirim.']);
        }

        foreach ($pendingLogs as $log) {
            \App\Jobs\SendEProductBroadcastJob::dispatch($log->user, $product, $log->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil memasukkan ' . $pendingLogs->count() . ' email ke dalam antrean pengiriman.',
            'batch_count' => $pendingLogs->count()
        ]);
    }

    /**
     * DAFTAR PENERIMA BROADCAST BESERTA STATUS
     */
    public function broadcastRecipients(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);

        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $query = \App\Models\EproductBroadcastLog::with('user:id,name,email')
            ->where('e_product_id', $id);

        // Filter by status (optional)
        if ($request->has('status') && in_array($request->status, ['pending', 'sent', 'failed'])) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByRaw("FIELD(status, 'sent', 'failed', 'pending')")
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}