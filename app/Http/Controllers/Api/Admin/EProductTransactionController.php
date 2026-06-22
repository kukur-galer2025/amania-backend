<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EProductPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class EProductTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            $query = EProductPurchase::with([
                'buyer:id,name,email,phone', 
                'items.product:id,title,price,user_id' 
            ]);

            if ($user && $user->role === 'creator') {
                $query->whereHas('items.product', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            $transactions = $query->latest()->get();

            $stats = [
                'total_revenue' => (int) $transactions->whereIn('status', ['PAID', 'SETTLED'])->sum('amount'),
                'paid_count'    => $transactions->whereIn('status', ['PAID', 'SETTLED'])->count(),
                'unpaid_count'  => $transactions->where('status', 'UNPAID')->count(),
                'expired_count' => $transactions->where('status', 'EXPIRED')->count(),
            ];

            $formattedTransactions = $transactions->map(function ($tx) {
                return [
                    'id'               => $tx->id,
                    'reference'        => $tx->reference,
                    'tripay_reference' => $tx->tripay_reference, 
                    'checkout_url'     => $tx->checkout_url,     
                    'payment_method'   => $tx->payment_method,   
                    'payment_proof'    => $tx->payment_proof ? url('storage/' . $tx->payment_proof) : null,
                    'amount'           => $tx->amount,
                    'status'           => $tx->status,
                    'created_at'       => $tx->created_at,
                    'buyer'            => $tx->buyer,
                    // 🔥 PERBAIKAN: Tambahkan pengaman agar tidak error jika items kosong 🔥
                    'product_names'    => $tx->items && $tx->items->count() > 0 
                                            ? $tx->items->map(function($item) {
                                                return $item->product ? $item->product->title : 'Produk Dihapus';
                                              })->implode(', ')
                                            : 'Tidak ada item'
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data transaksi E-Produk berhasil diambil.',
                'stats'   => $stats,
                'data'    => $formattedTransactions
            ], 200);

        } catch (\Exception $e) {
            Log::error('Admin E-Product Trx Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data transaksi.'
            ], 500);
        }
    }

    public function markAsPaid($id)
    {
        try {
            $transaction = EProductPurchase::with('items')->findOrFail($id);
            
            if (in_array($transaction->status, ['PAID', 'SETTLED'])) {
                return response()->json(['success' => false, 'message' => 'Transaksi ini sudah lunas sebelumnya.']);
            }

            $transaction->update(['status' => 'PAID']);

            if ($transaction->items && $transaction->items->count() > 0) {
                $purchasedProductIds = $transaction->items->pluck('e_product_id')->toArray();

                if (!empty($purchasedProductIds)) {
                    $unpaidTransactionsToCancel = EProductPurchase::where('user_id', $transaction->user_id)
                        ->where('id', '!=', $transaction->id)
                        ->where('status', 'UNPAID')
                        ->whereHas('items', function ($query) use ($purchasedProductIds) {
                            $query->whereIn('e_product_id', $purchasedProductIds);
                        })
                        ->get();

                    foreach ($unpaidTransactionsToCancel as $unpaidTx) {
                        $unpaidTx->update(['status' => 'EXPIRED']);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil ditandai LUNAS secara manual.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $currentUser = $request->user();
            $status = $request->query('status', 'all');
            $search = $request->query('search', '');

            $query = EProductPurchase::with(['buyer', 'items.product']);

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhereHas('buyer', function($qb) use ($search) {
                          $qb->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $transactions = $query->latest()->get();
            $totalRevenue = $transactions->whereIn('status', ['PAID', 'SETTLED'])->sum('amount');

            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: sans-serif; font-size: 11px; color: #333; }
                    .header { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
                    .title { font-size: 18px; font-weight: bold; margin: 0; color: #1e293b; }
                    .info { width: 100%; margin-bottom: 15px; }
                    .data-table { width: 100%; border-collapse: collapse; }
                    .data-table th { background-color: #f8fafc; padding: 10px; border: 1px solid #e2e8f0; text-align: left; }
                    .data-table td { padding: 8px; border: 1px solid #e2e8f0; vertical-align: top; }
                    .status-paid { color: #16a34a; font-weight: bold; }
                    .status-unpaid { color: #d97706; font-weight: bold; }
                    .footer { margin-top: 20px; text-align: right; font-size: 10px; color: #64748b; }
                    .summary-box { background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <p class="title">LAPORAN PENJUALAN E-PRODUK</p>
                    <p style="margin:5px 0;">Amania Institute Professional Platform</p>
                </div>

                <div class="summary-box">
                    <table width="100%">
                        <tr>
                            <td><strong>Dicetak Oleh:</strong> '.$currentUser->name.'</td>
                            <td align="right"><strong>Periode:</strong> Semua Data</td>
                        </tr>
                        <tr>
                            <td><strong>Total Transaksi:</strong> '.count($transactions).' Data</td>
                            <td align="right"><strong>Total Omset (Lunas):</strong> Rp '.number_format($totalRevenue, 0, ',', '.').'</td>
                        </tr>
                    </table>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Invoice</th>
                            <th width="25%">Pembeli</th>
                            <th width="25%">Produk</th>
                            <th width="15%">Nominal</th>
                            <th width="15%">Status</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($transactions as $idx => $tx) {
                $statusColor = ($tx->status == 'PAID' || $tx->status == 'SETTLED') ? 'status-paid' : 'status-unpaid';
                
                // 🔥 PERBAIKAN PENGAMAN UNTUK PDF 🔥
                $productNames = $tx->items && $tx->items->count() > 0 
                    ? $tx->items->map(function($item) {
                        return $item->product ? $item->product->title : 'Produk Dihapus';
                      })->implode(',<br>')
                    : 'Tidak ada item';

                $html .= '
                        <tr>
                            <td align="center">'.($idx + 1).'</td>
                            <td>'.$tx->reference.'<br><small style="color:#94a3b8">'. $tx->created_at->format('d/m/Y H:i') .'</small></td>
                            <td>
                                <strong>'.($tx->buyer->name ?? 'User').'</strong><br>
                                '.($tx->buyer->email ?? '-').'<br>
                                WA: '.($tx->buyer->phone ?? '-').'
                            </td>
                            <td>'.$productNames.'</td>
                            <td>Rp '.number_format($tx->amount, 0, ',', '.').'</td>
                            <td class="'.$statusColor.'">'.strtoupper($tx->status).'</td>
                        </tr>';
            }

            $html .= '
                    </tbody>
                </table>
                <div class="footer">Dicetak pada: '.date('d/m/Y H:i:s').' WIB</div>
            </body>
            </html>';

            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            return $pdf->download('Laporan_EProduct_'.date('YmdHis').'.pdf');

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}