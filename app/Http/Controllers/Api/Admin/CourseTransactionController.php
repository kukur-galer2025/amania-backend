<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;

class CourseTransactionController extends Controller
{
    public function index()
    {
        $transactions = CourseEnrollment::with(['user', 'course'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }
}
