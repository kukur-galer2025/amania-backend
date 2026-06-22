<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;

class CourseTransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        $query = CourseEnrollment::with(['user', 'course']);

        if ($user && $user->role === 'creator') {
            $query->whereHas('course', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $transactions = $query->latest()->get();

        $formatted = $transactions->map(function ($tx) {
            $txArray = $tx->toArray();
            $txArray['payment_proof'] = $tx->payment_proof ? url('storage/' . $tx->payment_proof) : null;
            return $txArray;
        });

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    public function markAsPaid($id)
    {
        try {
            $transaction = CourseEnrollment::findOrFail($id);
            $transaction->update(['status' => 'PAID']);
            return response()->json(['success' => true, 'message' => 'Transaksi kursus berhasil ditandai LUNAS secara manual.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }
}
