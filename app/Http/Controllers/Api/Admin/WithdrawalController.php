<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Withdrawal;
use App\Models\CourseEnrollment;
use App\Models\EProductPurchase;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    private function calculateCreatorStats($userId)
    {
        $courseRevenue = CourseEnrollment::whereHas('course', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->sum('amount');

        $eproductRevenue = EProductPurchase::whereHas('items.product', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->sum('amount');

        $totalRevenue = $courseRevenue + $eproductRevenue;

        $totalWithdrawn = Withdrawal::where('user_id', $userId)->where('status', 'approved')->sum('amount');
        $totalPending = Withdrawal::where('user_id', $userId)->where('status', 'pending')->sum('amount');

        $availableBalance = $totalRevenue - ($totalWithdrawn + $totalPending);

        return [
            'total_revenue' => $totalRevenue,
            'total_withdrawn' => $totalWithdrawn,
            'total_pending' => $totalPending,
            'available_balance' => $availableBalance,
        ];
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'creator') {
            return response()->json(['success' => true, 'data' => $this->calculateCreatorStats($user->id)]);
        }

        // For admin: global stats
        return response()->json([
            'success' => true,
            'data' => [
                'total_pending_requests' => Withdrawal::where('status', 'pending')->count(),
                'total_pending_amount' => Withdrawal::where('status', 'pending')->sum('amount'),
                'total_paid_amount' => Withdrawal::where('status', 'approved')->sum('amount'),
            ]
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Withdrawal::with('user:id,name,email,phone')->orderBy('created_at', 'desc');

        if ($user->role === 'creator') {
            $query->where('user_id', $user->id);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'creator') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:50000',
            'bank_name' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
        ]);

        $stats = $this->calculateCreatorStats($user->id);
        if ($request->amount > $stats['available_balance']) {
            return response()->json(['success' => false, 'message' => 'Saldo tidak mencukupi.'], 400);
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
            'status' => 'pending'
        ]);

        return response()->json(['success' => true, 'message' => 'Penarikan berhasil diajukan.', 'data' => $withdrawal]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'transfer_proof' => 'required|image|max:2048'
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Hanya status pending yang bisa diproses.'], 400);
        }

        $path = $request->file('transfer_proof')->store('transfer_proofs', 'public');

        $withdrawal->update([
            'status' => 'approved',
            'transfer_proof' => '/storage/' . $path
        ]);

        return response()->json(['success' => true, 'message' => 'Penarikan disetujui.']);
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'notes' => 'required|string|max:500'
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Hanya status pending yang bisa diproses.'], 400);
        }

        $withdrawal->update([
            'status' => 'rejected',
            'notes' => $request->notes
        ]);

        return response()->json(['success' => true, 'message' => 'Penarikan ditolak.']);
    }
}
