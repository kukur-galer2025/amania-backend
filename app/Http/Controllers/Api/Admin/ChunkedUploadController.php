<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    /**
     * 🔥 RECEIVE A SINGLE CHUNK
     * Frontend mengirim file pecahan kecil (2MB) satu per satu.
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'chunk'       => 'required|file',
            'upload_id'   => 'required|string|max:100',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
        ]);

        $uploadId   = $request->upload_id;
        $chunkIndex = $request->chunk_index;
        $chunk      = $request->file('chunk');

        // Simpan chunk ke folder temporary
        $chunkDir = "chunks/{$uploadId}";
        $chunk->storeAs($chunkDir, "chunk_{$chunkIndex}", 'local');

        return response()->json([
            'success'     => true,
            'chunk_index' => $chunkIndex,
            'message'     => "Chunk {$chunkIndex} diterima.",
        ]);
    }

    /**
     * 🔥 MERGE ALL CHUNKS INTO ONE FILE
     * Setelah semua chunk selesai diupload, frontend memanggil endpoint ini
     * untuk menggabungkan semua chunk menjadi 1 file utuh.
     */
    public function mergeChunks(Request $request)
    {
        $request->validate([
            'upload_id'    => 'required|string|max:100',
            'total_chunks' => 'required|integer|min:1',
            'file_name'    => 'required|string|max:255',
            'destination'  => 'required|string|in:courses/lessons/videos,courses/lessons/files,e-products/materials',
        ]);

        $uploadId    = $request->upload_id;
        $totalChunks = $request->total_chunks;
        $fileName    = $request->file_name;
        $destination = $request->destination;

        $chunkDir = Storage::disk('local')->path("chunks/{$uploadId}");

        // Verifikasi semua chunk ada
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists("{$chunkDir}/chunk_{$i}")) {
                return response()->json([
                    'success' => false,
                    'message' => "Chunk {$i} tidak ditemukan. Upload ulang file.",
                ], 422);
            }
        }

        // Generate final filename
        $ext = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'mp4';
        $finalName = Str::uuid() . '.' . $ext;
        $finalRelativePath = "{$destination}/{$finalName}";
        $finalFullPath = Storage::disk('public')->path($finalRelativePath);

        // Pastikan directory tujuan ada
        $dir = dirname($finalFullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Gabungkan semua chunk
        $output = fopen($finalFullPath, 'wb');
        if (!$output) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat file output.',
            ], 500);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            $chunkData = fopen($chunkPath, 'rb');
            if ($chunkData) {
                stream_copy_to_stream($chunkData, $output);
                fclose($chunkData);
            }
        }
        fclose($output);

        // Hapus folder chunks temporary
        $this->deleteDirectory($chunkDir);

        return response()->json([
            'success'   => true,
            'file_path' => $finalRelativePath,
            'file_name' => $fileName,
            'file_size' => filesize($finalFullPath),
            'message'   => 'File berhasil digabungkan!',
        ]);
    }

    /**
     * Helper: Hapus directory beserta isinya
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
