<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $statusFilter = $request->query('status', 'all');
        $eventIdFilter = $request->query('event_id', 'all');
        
        $tierFilter = $request->query('tier', 'all');

        $queryEvent = Event::query();

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer') {
            $queryEvent->where('user_id', $currentUser->id);
        }

        // A. Dropdown Frontend
        $allEvents = (clone $queryEvent)->select('id', 'title')->orderBy('created_at', 'desc')->get();

        // B. Query Event untuk Tabel
        if ($eventIdFilter !== 'all') {
            $queryEvent->where('id', $eventIdFilter);
        }

        $events = $queryEvent->withCount(['registrations as total_peserta' => function ($q) use ($statusFilter, $tierFilter) {
                if ($statusFilter !== 'all') {
                    $q->where('status', $statusFilter);
                } else {
                    $q->whereIn('status', ['verified', 'pending', 'rejected']); 
                }
                
                if ($tierFilter !== 'all') {
                    $q->where('tier', $tierFilter);
                }
            }])
            ->withSum(['registrations as total_pendapatan' => function ($q) use ($statusFilter, $tierFilter) {
                if ($statusFilter !== 'all') {
                    $q->where('status', $statusFilter);
                } else {
                    $q->whereIn('status', ['verified', 'pending', 'rejected']);
                }
                
                if ($tierFilter !== 'all') {
                    $q->where('tier', $tierFilter);
                }
            }], 'total_amount')
            ->orderBy('created_at', 'desc')
            ->get();

        $globalStats = [
            'total_event' => $events->count(),
            'total_semua_peserta' => (int) $events->sum('total_peserta'),
            'total_semua_pendapatan' => (int) $events->sum('total_pendapatan')
        ];

        return response()->json([
            'success' => true, 
            'stats' => $globalStats, 
            'events' => $events,      
            'all_events' => $allEvents 
        ]);
    }

    public function export(Request $request)
    {
        $currentUser = $request->user();
        
        // 🔥 PERBAIKAN: Menambahkan 'phone' agar bisa ditarik ke dalam laporan PDF 🔥
        $query = Registration::with(['user:id,name,email,phone', 'event:id,title']);

        // 🔥 PROTEKSI MULTI-TENANT EKSPORT 🔥
        if ($currentUser->role === 'organizer') {
            $query->whereHas('event', function($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        // Filter Event
        $eventName = "Semua Program Event";
        if ($request->has('event_id') && $request->event_id != 'all') {
            $query->where('event_id', $request->event_id);
            $event = Event::find($request->event_id);
            if($event) $eventName = $event->title;
        }

        // Filter Status
        $statusName = "Semua Status";
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
            $statusName = strtoupper($request->status);
        }

        // Filter Tipe Tiket
        $tierName = "Semua Tipe";
        if ($request->has('tier') && $request->tier != 'all') {
            $query->where('tier', $request->tier);
            $tierName = strtoupper($request->tier);
        }

        $registrations = $query->orderBy('created_at', 'desc')->get();
        $totalPendapatan = $registrations->sum('total_amount'); 

        // 🔥 Buat Desain HTML untuk PDF 🔥
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: sans-serif; font-size: 11px; color: #333; }
                .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #4f46e5; }
                .title { font-size: 18px; font-weight: bold; color: #1e293b; margin: 0; }
                .subtitle { font-size: 11px; color: #64748b; margin-top: 5px; }
                .info-table { width: 100%; margin-bottom: 20px; }
                .info-table td { padding: 3px; font-size: 11px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .data-table th { background-color: #f1f5f9; color: #475569; padding: 8px; text-align: left; border: 1px solid #cbd5e1; font-size: 10px; text-transform: uppercase; }
                .data-table td { padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 10px; vertical-align: top; }
                .status-verified { color: #16a34a; font-weight: bold; }
                .status-pending { color: #d97706; font-weight: bold; }
                .status-rejected { color: #dc2626; font-weight: bold; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #64748b; }
                .contact-info { font-size: 9px; color: #475569; margin-top: 3px; line-height: 1.3; }
            </style>
        </head>
        <body>
            <div class="header">
                <p class="title">LAPORAN PENDAFTARAN & TRANSAKSI</p>
                <p class="subtitle">EduTech Amania Professional Platform</p>
                <p class="subtitle">Dicetak Oleh: ' . $currentUser->name . ' (' . strtoupper($currentUser->role) . ')</p>
            </div>
            
            <table class="info-table">
                <tr>
                    <td width="15%"><strong>Filter Event</strong></td>
                    <td width="35%">: ' . $eventName . '</td>
                    <td width="20%"><strong>Total Data</strong></td>
                    <td width="30%">: ' . count($registrations) . ' Pendaftar</td>
                </tr>
                <tr>
                    <td><strong>Filter Status</strong></td>
                    <td>: ' . $statusName . '</td>
                    <td><strong>Omset Terkumpul</strong></td>
                    <td>: Rp ' . number_format($totalPendapatan, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td><strong>Tipe Tiket</strong></td>
                    <td>: ' . $tierName . '</td>
                    <td colspan="2"></td>
                </tr>
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="12%">Kode Tiket</th>
                        <th width="24%">Data Peserta</th>
                        <th width="21%">Program Event</th>
                        <th width="10%">Tipe Tiket</th>
                        <th width="13%">Waktu Daftar</th>
                        <th width="15%" class="text-right">Nominal / Status</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($registrations as $reg) {
            $statusClass = 'status-' . strtolower($reg->status);
            $tierLabel = strtoupper($reg->tier ?? 'BASIC');
            
            $html .= '
                    <tr>
                        <td class="text-center">' . $no++ . '</td>
                        <td>' . ($reg->ticket_code ?? '-') . '</td>
                        <td>
                            <strong>' . ($reg->user->name ?? 'Unknown') . '</strong>
                            <div class="contact-info">
                                ' . ($reg->user->email ?? '-') . '<br>
                                WA: ' . ($reg->user->phone ?? '-') . '
                            </div>
                        </td>
                        <td>' . ($reg->event->title ?? 'Unknown') . '</td>
                        <td>' . $tierLabel . '</td>
                        <td>' . $reg->created_at->format('d/m/Y H:i') . '</td>
                        <td class="text-right">
                            Rp ' . number_format($reg->total_amount, 0, ',', '.') . '<br>
                            <span class="' . $statusClass . '">' . strtoupper($reg->status) . '</span>
                        </td>
                    </tr>';
        }

        if(count($registrations) == 0){
             $html .= '<tr><td colspan="7" align="center" style="padding: 20px;">Tidak ada data pada filter ini.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                Dicetak pada: ' . date('d F Y H:i:s') . ' WIB
            </div>
        </body>
        </html>';

        $fileName = "Laporan_Amania_" . date('Ymd_His') . ".pdf";
        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');
        return $pdf->download($fileName);
    }
}