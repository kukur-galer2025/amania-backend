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
        $course = \App\Models\Course::findOrFail($courseId);
        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

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
            $rules['youtube_url'] = 'nullable|string';
            $rules['video_upload'] = 'nullable|file|mimes:mp4,webm,mov,avi|max:524288'; // 512MB
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

        // 🔥 Handle self-hosted video upload (direct or chunked)
        if ($type === 'video') {
            if ($request->hasFile('video_upload')) {
                // Direct upload (file kecil)
                $video = $request->file('video_upload');
                $data['video_path'] = $video->store('courses/lessons/videos', 'public');
                $data['video_disk'] = 'public';
            } elseif ($request->filled('video_path')) {
                // Chunked upload (file sudah di-merge oleh ChunkedUploadController)
                $data['video_path'] = $request->video_path;
                $data['video_disk'] = 'public';
            }
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
        $course = \App\Models\Course::findOrFail($courseId);
        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

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
            $rules['youtube_url'] = 'nullable|string';
            $rules['video_upload'] = 'nullable|file|mimes:mp4,webm,mov,avi|max:524288'; // 512MB
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

        // 🔥 Handle self-hosted video upload on update (direct or chunked)
        if ($type === 'video') {
            $newVideoPath = null;
            if ($request->hasFile('video_upload')) {
                $video = $request->file('video_upload');
                $newVideoPath = $video->store('courses/lessons/videos', 'public');
            } elseif ($request->filled('video_path') && $request->video_path !== $lesson->video_path) {
                $newVideoPath = $request->video_path;
            }

            if ($newVideoPath) {
                // Delete old video if exists
                if ($lesson->video_path && Storage::disk($lesson->video_disk ?? 'public')->exists($lesson->video_path)) {
                    Storage::disk($lesson->video_disk ?? 'public')->delete($lesson->video_path);
                }
                $data['video_path'] = $newVideoPath;
                $data['video_disk'] = 'public';
            }
        }

        // Clear video fields if type changed away from video
        if ($type !== 'video') {
            if ($lesson->video_path && Storage::disk($lesson->video_disk ?? 'public')->exists($lesson->video_path)) {
                Storage::disk($lesson->video_disk ?? 'public')->delete($lesson->video_path);
            }
            $data['video_path'] = null;
            $data['youtube_url'] = null;
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
    public function destroy(Request $request, $courseId, $lessonId)
    {
        $course = \App\Models\Course::findOrFail($courseId);
        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $lesson = CourseLesson::findOrFail($lessonId);

        // Delete physical file if exists
        if ($lesson->file_path && Storage::disk('public')->exists($lesson->file_path)) {
            Storage::disk('public')->delete($lesson->file_path);
        }
        // Delete video file if exists
        if ($lesson->video_path && Storage::disk($lesson->video_disk ?? 'public')->exists($lesson->video_path)) {
            Storage::disk($lesson->video_disk ?? 'public')->delete($lesson->video_path);
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
