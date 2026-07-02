<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubtask;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TaskController extends Controller
{
    // ==========================================
    // TASK CRUD
    // ==========================================
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Task::with(['subtasks', 'comments.user'])
            ->where('user_id', $user->id);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('label') && $request->label !== 'all') {
            $query->where('label_name', $request->label);
        }

        $tasks = $query->orderByRaw("FIELD(status, 'in_progress', 'pending', 'completed')")
                       ->orderBy('position')
                       ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                       ->orderBy('due_date', 'asc')
                       ->get()
                       ->map(function ($task) {
                           $total = $task->subtasks->count();
                           $done  = $task->subtasks->where('is_completed', true)->count();
                           $task->subtask_progress = $total > 0 ? round(($done / $total) * 100) : null;
                           $task->subtask_count = $total;
                           $task->subtask_done = $done;
                           $task->comments_count = $task->comments->count();
                           return $task;
                       });

        // Stats
        $allTasks = Task::where('user_id', $user->id);
        $stats = [
            'total'       => (clone $allTasks)->count(),
            'pending'     => (clone $allTasks)->where('status', 'pending')->count(),
            'in_progress' => (clone $allTasks)->where('status', 'in_progress')->count(),
            'completed'   => (clone $allTasks)->where('status', 'completed')->count(),
        ];

        // Label unik untuk filter
        $labels = Task::where('user_id', $user->id)
            ->whereNotNull('label_name')
            ->select('label_name', 'label_color')
            ->distinct()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $tasks,
            'stats'   => $stats,
            'labels'  => $labels,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority'    => 'required|in:low,medium,high',
            'due_date'    => 'nullable|date',
            'label_name'  => 'nullable|string|max:50',
            'label_color' => 'nullable|string|max:20',
            'status'      => 'nullable|in:pending,in_progress,completed',
        ]);

        $maxPos = Task::where('user_id', auth()->id())
            ->where('status', $request->status ?? 'pending')
            ->max('position') ?? 0;

        $task = Task::create([
            'user_id'     => auth()->id(),
            'title'       => $request->title,
            'description' => $request->description,
            'priority'    => $request->priority,
            'due_date'    => $request->due_date,
            'label_name'  => $request->label_name,
            'label_color' => $request->label_color,
            'status'      => $request->status ?? 'pending',
            'position'    => $maxPos + 1,
        ]);

        if ($request->has('subtasks') && is_array($request->subtasks)) {
            foreach ($request->subtasks as $index => $sub) {
                if (is_array($sub) && !empty(trim($sub['title'] ?? ''))) {
                    $task->subtasks()->create([
                        'title' => trim($sub['title']),
                        'position' => $index + 1
                    ]);
                } elseif (is_string($sub) && !empty(trim($sub))) {
                    $task->subtasks()->create([
                        'title' => trim($sub),
                        'position' => $index + 1
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil ditambahkan.',
            'data'    => $task->load(['subtasks', 'comments.user']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority'    => 'required|in:low,medium,high',
            'due_date'    => 'nullable|date',
            'label_name'  => 'nullable|string|max:50',
            'label_color' => 'nullable|string|max:20',
            'subtasks'    => 'nullable|array',
        ]);

        $task->update($request->only(['title', 'description', 'priority', 'due_date', 'label_name', 'label_color']));

        // Handle subtasks update (sync)
        if ($request->has('subtasks') && is_array($request->subtasks)) {
            // Hapus yang lama, insert yang baru agar urutan sesuai
            $task->subtasks()->delete();
            foreach ($request->subtasks as $index => $sub) {
                // If it's an array with title and is_completed, keep the status
                if (is_array($sub) && !empty(trim($sub['title'] ?? ''))) {
                    $task->subtasks()->create([
                        'title' => trim($sub['title']),
                        'is_completed' => $sub['is_completed'] ?? false,
                        'position' => $index + 1
                    ]);
                } elseif (is_string($sub) && !empty(trim($sub))) {
                    $task->subtasks()->create([
                        'title' => trim($sub),
                        'position' => $index + 1
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil diperbarui.',
            'data'    => $task->fresh()->load(['subtasks', 'comments.user']),
        ]);
    }

    public function toggleStatus($id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);

        $nextStatus = match ($task->status) {
            'pending'     => 'in_progress',
            'in_progress' => 'completed',
            'completed'   => 'pending',
        };

        $task->update([
            'status'       => $nextStatus,
            'completed_at' => $nextStatus === 'completed' ? Carbon::now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status task → ' . $nextStatus,
            'data'    => $task->fresh()->load(['subtasks', 'comments.user']),
        ]);
    }

    // Kanban: pindahkan task ke kolom/status lain dengan posisi baru
    public function moveTask(Request $request, $id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'status'   => 'required|in:pending,in_progress,completed',
            'position' => 'required|integer|min:0',
        ]);

        $task->update([
            'status'       => $request->status,
            'position'     => $request->position,
            'completed_at' => $request->status === 'completed' ? Carbon::now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task dipindahkan.',
            'data'    => $task->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($id);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dihapus.',
        ]);
    }

    // ==========================================
    // SUBTASKS
    // ==========================================
    public function addSubtask(Request $request, $taskId)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($taskId);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $maxPos = $task->subtasks()->max('position') ?? 0;

        $subtask = $task->subtasks()->create([
            'title'    => $request->title,
            'position' => $maxPos + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subtask ditambahkan.',
            'data'    => $subtask,
        ]);
    }

    public function toggleSubtask($taskId, $subtaskId)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($taskId);
        $subtask = $task->subtasks()->findOrFail($subtaskId);

        $subtask->update(['is_completed' => !$subtask->is_completed]);

        return response()->json([
            'success' => true,
            'message' => $subtask->is_completed ? 'Subtask selesai.' : 'Subtask dibuka kembali.',
            'data'    => $subtask->fresh(),
        ]);
    }

    public function deleteSubtask($taskId, $subtaskId)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($taskId);
        $task->subtasks()->findOrFail($subtaskId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subtask dihapus.',
        ]);
    }

    // ==========================================
    // COMMENTS
    // ==========================================
    public function addComment(Request $request, $taskId)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($taskId);

        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = $task->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komentar ditambahkan.',
            'data'    => $comment->load('user'),
        ]);
    }

    public function deleteComment($taskId, $commentId)
    {
        $task = Task::where('user_id', auth()->id())->findOrFail($taskId);
        $comment = $task->comments()->findOrFail($commentId);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komentar dihapus.',
        ]);
    }
}
