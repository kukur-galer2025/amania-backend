<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use App\Notifications\CourseStatusNotification;

class CourseTransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        $query = CourseEnrollment::with(['user', 'course.user']);

        if ($user && $user->role === 'creator') {
            $query->whereHas('course', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $transactions = $query->latest()->get();

        // 🔥 Kumpulkan kreator unik untuk dropdown filter 🔥
        $creatorsMap = [];

        $formatted = $transactions->map(function ($tx) use (&$creatorsMap) {
            // Kumpulkan kreator
            if ($tx->course && $tx->course->user) {
                $creatorsMap[$tx->course->user->id] = $tx->course->user->name;
            }

            $txArray = $tx->toArray();
            $txArray['payment_proof'] = $tx->payment_proof ? url('storage/' . $tx->payment_proof) : null;
            return $txArray;
        });

        $creatorsList = collect($creatorsMap)->map(function ($name, $id) {
            return ['id' => $id, 'name' => $name];
        })->values();

        return response()->json(['success' => true, 'creators' => $creatorsList, 'data' => $formatted]);
    }

    public function markAsPaid($id)
    {
        try {
            $user = auth()->user();
            $transaction = CourseEnrollment::with('course')->findOrFail($id);

            if ($user->role === 'creator' && $transaction->course->user_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            $transaction->update(['status' => 'PAID']);
            
            $transaction->user->notify(new CourseStatusNotification($transaction, 'verified'));
            
            return response()->json(['success' => true, 'message' => 'Transaksi kursus berhasil ditandai LUNAS secara manual.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $user = auth()->user();
            $transaction = CourseEnrollment::with('course')->findOrFail($id);
            
            if ($user->role === 'creator' && $transaction->course->user_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            if (in_array($transaction->status, ['PAID', 'SETTLED'])) {
                return response()->json(['success' => false, 'message' => 'Transaksi ini sudah lunas sebelumnya.']);
            }

            $transaction->update([
                'status' => 'FAILED',
                'rejection_reason' => $request->reason
            ]);

            $transaction->user->notify(new CourseStatusNotification($transaction, 'rejected'));

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil ditolak.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}
