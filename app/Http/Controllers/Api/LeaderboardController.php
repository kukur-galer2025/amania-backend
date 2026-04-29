<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EProductPurchase; 
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $category = $request->query('category', 'all');

        // Filter tanggal untuk Event
        $filterEvent = function ($query) use ($filter) {
            $query->where('status', 'verified');
            if ($filter === 'month') {
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
            } elseif ($filter === 'week') {
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            }
        };

        // Query Dasar (Ambil total event & e-product)
        $leadersQuery = User::select(['users.id', 'users.name', 'users.email', 'users.avatar'])
            ->withCount(['registrations' => $filterEvent])
            ->addSelect(['e_products_count' => EProductPurchase::selectRaw('count(*)')
                ->whereColumn('e_product_purchases.user_id', 'users.id')
                ->whereIn('e_product_purchases.status', ['PAID', 'success', 'SETTLED']) 
                ->when($filter === 'month', fn($q) => $q->whereMonth('e_product_purchases.created_at', Carbon::now()->month)->whereYear('e_product_purchases.created_at', Carbon::now()->year))
                ->when($filter === 'week', fn($q) => $q->whereBetween('e_product_purchases.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]))
            ]);

        // 🔥 LOGIKA STRICT FILTERING (TENDANG YANG NILAINYA 0) & SORTING
        if ($category === 'event') {
            // Hanya tampilkan yang Event-nya > 0
            $leadersQuery->having('registrations_count', '>', 0)
                         ->orderBy('registrations_count', 'desc');
        } elseif ($category === 'eproduct') {
            // Hanya tampilkan yang E-Product-nya > 0
            $leadersQuery->havingRaw('IFNULL(e_products_count, 0) > 0')
                         ->orderByRaw('IFNULL(e_products_count, 0) DESC');
        } else {
            // Global: Tampilkan jika gabungan (Event + E-Product) > 0
            $leadersQuery->havingRaw('(registrations_count + IFNULL(e_products_count, 0)) > 0')
                         ->orderByRaw('(registrations_count + IFNULL(e_products_count, 0)) DESC');
        }

        $leaders = $leadersQuery->take(50)->get();

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'category' => $category,
            'data' => $leaders
        ]);
    }
}