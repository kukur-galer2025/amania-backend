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

        $events = $queryEvent->withCount(['registrations as total_peserta' => function ($q) use ($statusFilter) {
                if ($statusFilter !== 'all') {
                    $q->where('status', $statusFilter);
                } else {
                    $q->whereIn('status', ['verified', 'pending', 'rejected']); 
                }
            }])
            ->withSum(['registrations as total_pendapatan' => function ($q) use ($statusFilter) {
                if ($statusFilter !== 'all') {
                    $q->where('status', $statusFilter);
                } else {
                    $q->whereIn('status', ['verified', 'pending', 'rejected']);
                }
            }], 'total_amount')
            ->orderBy('created_at', 'desc')
            ->get();

        $globalStats = [
            'total_event' => $events->count(),
            'total_semua_peserta' => $events->sum('total_peserta'),
            'total_semua_pendapatan' => $events->sum('total_pendapatan')
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
        $query = Registration::with(['user:id,name,email', 'event:id,title']);

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

        $registrations = $query->orderBy('created_at', 'desc')->get();
        $totalPendapatan = $registrations->sum('total_amount'); 

        // 🔥 Buat Desain HTML untuk PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: sans-serif; font-size: 12px; color: #333; }
                .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #4f46e5; }
                .title { font-size: 20px; font-weight: bold; color: #1e293b; margin: 0; }
                .subtitle { font-size: 12px; color: #64748b; margin-top: 5px; }
                .info-table { width: 100%; margin-bottom: 20px; }
                .info-table td { padding: 3px; font-size: 12px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .data-table th { background-color: #f1f5f9; color: #475569; padding: 10px; text-align: left; border: 1px solid #cbd5e1; font-size: 11px; text-transform: uppercase; }
                .data-table td { padding: 8px 10px; border: 1px solid #cbd5e1; }
                .status-verified { color: #16a34a; font-weight: bold; }
                .status-pending { color: #d97706; font-weight: bold; }
                .status-rejected { color: #dc2626; font-weight: bold; }
                .text-right { text-align: right; }
                .footer { margin-top: 30px; text-align: right; font-size: 11px; color: #64748b; }
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
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Kode Tiket</th>
                        <th width="22%">Nama Peserta</th>
                        <th width="28%">Program Event</th>
                        <th width="15%">Waktu Daftar</th>
                        <th width="15%" class="text-right">Nominal / Status</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($registrations as $reg) {
            $statusClass = 'status-' . strtolower($reg->status);
            $html .= '
                    <tr>
                        <td align="center">' . $no++ . '</td>
                        <td>' . ($reg->ticket_code ?? '-') . '</td>
                        <td>' . ($reg->user->name ?? 'Unknown') . '</td>
                        <td>' . ($reg->event->title ?? 'Unknown') . '</td>
                        <td>' . $reg->created_at->format('d/m/Y H:i') . '</td>
                        <td class="text-right">
                            Rp ' . number_format($reg->total_amount, 0, ',', '.') . '<br>
                            <span class="' . $statusClass . '">' . strtoupper($reg->status) . '</span>
                        </td>
                    </tr>';
        }

        if(count($registrations) == 0){
             $html .= '<tr><td colspan="6" align="center" style="padding: 20px;">Tidak ada data pada filter ini.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                Dicetak pada: ' . date('d F Y H:i:s') . ' WIB
            </div>
        </body>
        </html>';

        $fileName = "Laporan_EduTech_" . date('Ymd_His') . ".pdf";
        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');
        return $pdf->download($fileName);
    }
}