<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseLessonController extends Controller
{
    /**
     * TAMBAH LESSON KE SECTION
     */
    public function store(Request $request, $courseId)
    {
        $type = $request->input('type', 'video');

        $rules = [
            'course_section_id' => 'required|exists:course_sections,id',
            'title'             => 'required|string|max:255',
            'type'              => 'nullable|in:video,text,file',
            'duration_minutes'  => 'nullable|integer|min:0',
            'is_preview'        => 'nullable',
            'order'             => 'nullable|integer',
        ];

        if ($type === 'video') {
            $rules['youtube_url'] = 'required|string';
        } elseif ($type === 'text') {
            $rules['text_content'] = 'required|string';
        } elseif ($type === 'file') {
            $rules['file_upload'] = 'required|file|max:51200'; // 50MB
        }

        $request->validate($rules);

        $data = [
            'course_section_id' => $request->course_section_id,
            'title'             => $request->title,
            'type'              => $type,
            'youtube_url'       => $type === 'video' ? $request->youtube_url : null,
            'text_content'      => $type === 'text' ? $request->text_content : null,
            'duration_minutes'  => $request->duration_minutes ?? 0,
            'is_preview'        => filter_var($request->is_preview, FILTER_VALIDATE_BOOLEAN),
            'order'             => $request->order ?? (CourseLesson::where('course_section_id', $request->course_section_id)->max('order') + 1),
        ];

        // Handle file upload
        if ($type === 'file' && $request->hasFile('file_upload')) {
            $file = $request->file('file_upload');
            $data['file_path'] = $file->store('courses/lessons/files', 'public');
            $data['file_name'] = $file->getClientOriginalName();
        }

        $lesson = CourseLesson::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lesson berhasil ditambahkan!',
            'data'    => $lesson,
        ], 201);
    }

    /**
     * UPDATE LESSON
     */
    public function update(Request $request, $courseId, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);
        $type = $request->input('type', $lesson->type ?? 'video');

        $rules = [
            'title'            => 'required|string|max:255',
            'type'             => 'nullable|in:video,text,file',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_preview'       => 'nullable',
            'order'            => 'nullable|integer',
        ];

        if ($type === 'video') {
            $rules['youtube_url'] = 'required|string';
        } elseif ($type === 'text') {
            $rules['text_content'] = 'required|string';
        } elseif ($type === 'file') {
            $rules['file_upload'] = 'nullable|file|max:51200'; // optional on update
        }

        $request->validate($rules);

        $data = [
            'title'            => $request->title,
            'type'             => $type,
            'youtube_url'      => $type === 'video' ? $request->youtube_url : null,
            'text_content'     => $type === 'text' ? $request->text_content : null,
            'duration_minutes' => $request->duration_minutes ?? $lesson->duration_minutes,
            'is_preview'       => $request->has('is_preview') ? filter_var($request->is_preview, FILTER_VALIDATE_BOOLEAN) : $lesson->is_preview,
            'order'            => $request->order ?? $lesson->order,
        ];

        // Handle file upload on update
        if ($type === 'file' && $request->hasFile('file_upload')) {
            // Delete old file
            if ($lesson->file_path && Storage::disk('public')->exists($lesson->file_path)) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $file = $request->file('file_upload');
            $data['file_path'] = $file->store('courses/lessons/files', 'public');
            $data['file_name'] = $file->getClientOriginalName();
        }

        // Clear file fields if type changed away from file
        if ($type !== 'file') {
            if ($lesson->file_path && Storage::disk('public')->exists($lesson->file_path)) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $data['file_path'] = null;
            $data['file_name'] = null;
        }

        $lesson->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Lesson berhasil diperbarui!',
            'data'    => $lesson,
        ]);
    }

    /**
     * HAPUS LESSON
     */
    public function destroy($courseId, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);

        // Delete physical file if exists
        if ($lesson->file_path && Storage::disk('public')->exists($lesson->file_path)) {
            Storage::disk('public')->delete($lesson->file_path);
        }

        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lesson berhasil dihapus!',
        ]);
    }

    /**
     * DOWNLOAD FILE LESSON
     */
    public function downloadFile($courseId, $lessonId)
    {
        $lesson = CourseLesson::findOrFail($lessonId);

        if ($lesson->type !== 'file' || !$lesson->file_path || !Storage::disk('public')->exists($lesson->file_path)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
        }

        return Storage::disk('public')->download($lesson->file_path, $lesson->file_name ?? 'download');
    }
}
