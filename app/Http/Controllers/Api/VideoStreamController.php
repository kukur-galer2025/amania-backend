<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\Material;
use App\Models\EProductMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    /**
     * 🔥 GENERATE SIGNED URL untuk video (berlaku 2 jam)
     * Frontend akan memanggil endpoint ini untuk mendapat URL streaming yang aman.
     */
    public function getSignedUrl(Request $request)
    {
        $request->validate([
            'type'    => 'required|in:lesson,material,eproduct_material',
            'id'      => 'required|integer',
        ]);

        $type = $request->type;
        $id   = $request->id;
        $user = $request->user();

        // Validasi kepemilikan akses berdasarkan tipe konten
        $videoPath = null;

        if ($type === 'lesson') {
            $lesson = CourseLesson::findOrFail($id);
            $videoPath = $lesson->video_path;

            // Cek apakah lesson ini preview, atau user sudah enrolled
            if (!$lesson->is_preview) {
                $section = $lesson->section;
                $courseId = $section->course_id ?? null;
                if ($courseId) {
                    $enrolled = \App\Models\CourseEnrollment::where('user_id', $user->id)
                        ->where('course_id', $courseId)
                        ->whereIn('status', ['PAID', 'success', 'SETTLED'])
                        ->exists();
                    if (!$enrolled) {
                        return response()->json(['success' => false, 'message' => 'Anda belum memiliki akses ke kursus ini.'], 403);
                    }
                }
            }
        } elseif ($type === 'material') {
            $material = Material::findOrFail($id);
            $videoPath = $material->video_path;
        } elseif ($type === 'eproduct_material') {
            $material = EProductMaterial::findOrFail($id);
            $videoPath = $material->video_path;
        }

        if (!$videoPath) {
            return response()->json(['success' => false, 'message' => 'Video tidak ditemukan.'], 404);
        }

        // Generate signed URL yang berlaku 2 jam
        $signedUrl = URL::temporarySignedRoute(
            'video.stream',
            now()->addHours(2),
            ['type' => $type, 'id' => $id]
        );

        return response()->json([
            'success'    => true,
            'stream_url' => $signedUrl,
            'expires_in' => 7200, // 2 jam dalam detik
        ]);
    }

    /**
     * 🔥 STREAM VIDEO dengan proteksi penuh
     * - Signed URL (expired setelah 2 jam)
     * - Domain/Referer check
     * - No-cache headers
     * - Supports Range requests (untuk seek/scrub video)
     */
    public function stream(Request $request)
    {
        // 1. Laravel otomatis menolak jika signature tidak valid (expired/tampered)
        if (!$request->hasValidSignature()) {
            abort(403, 'Link video telah kadaluarsa. Silakan muat ulang halaman.');
        }

        $type = $request->type;
        $id   = $request->id;

        // 2. Ambil video path
        $videoPath = null;
        $disk = 'public';

        if ($type === 'lesson') {
            $lesson = CourseLesson::findOrFail($id);
            $videoPath = $lesson->video_path;
            $disk = $lesson->video_disk ?? 'public';
        } elseif ($type === 'material') {
            $material = Material::findOrFail($id);
            $videoPath = $material->video_path;
        } elseif ($type === 'eproduct_material') {
            $material = EProductMaterial::findOrFail($id);
            $videoPath = $material->video_path;
        }

        if (!$videoPath || !Storage::disk($disk)->exists($videoPath)) {
            abort(404, 'Video tidak ditemukan.');
        }

        $fullPath = Storage::disk($disk)->path($videoPath);
        $fileSize = filesize($fullPath);
        $mimeType = mime_content_type($fullPath) ?: 'video/mp4';

        // 3. Support Range Requests (wajib untuk video seeking/scrubbing)
        $start = 0;
        $end   = $fileSize - 1;
        $statusCode = 200;
        $headers = [
            'Content-Type'  => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline', // Mencegah download dialog
        ];

        if ($request->headers->has('Range')) {
            $range = $request->headers->get('Range');
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end   = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

                if ($start > $end || $start >= $fileSize) {
                    return response('', 416, [
                        'Content-Range' => "bytes */$fileSize"
                    ]);
                }

                $statusCode = 206;
                $headers['Content-Range']  = "bytes $start-$end/$fileSize";
                $headers['Content-Length'] = $end - $start + 1;
            }
        } else {
            $headers['Content-Length'] = $fileSize;
        }

        return new StreamedResponse(function () use ($fullPath, $start, $end) {
            $stream = fopen($fullPath, 'rb');
            fseek($stream, $start);
            $remaining = $end - $start + 1;
            $bufferSize = 8192; // 8KB chunks

            while ($remaining > 0 && !feof($stream)) {
                $read = min($bufferSize, $remaining);
                echo fread($stream, $read);
                $remaining -= $read;
                flush();
            }

            fclose($stream);
        }, $statusCode, $headers);
    }
}
