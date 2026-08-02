<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductOrderItem; // 🔥 WAJIB DI-IMPORT
use App\Models\EProductReview;
use Illuminate\Http\Request;

class EProductController extends Controller
{
    public function index(Request $request)
    {
        $query = EProduct::where('is_published', true)
            ->with(['author:id,name,avatar,role', 'category:id,name']) 
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest();

        $products = $query->get();
        $user = auth('sanctum')->user();

        if ($user) {
            // 🔥 TARIK DATA KEPEMILIKAN DARI TABEL ORDER ITEMS (DETAIL) 🔥
            $purchasedProductIds = EProductOrderItem::whereHas('purchase', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->whereIn('status', ['PAID', 'success', 'SETTLED']);
                })
                ->pluck('e_product_id')
                ->toArray();

            $products->map(function ($product) use ($purchasedProductIds) {
                $product->is_purchased = in_array($product->id, $purchasedProductIds);
                return $product;
            });
        } else {
            $products->map(function ($product) {
                $product->is_purchased = false;
                return $product;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function show($slug)
    {
        $product = EProduct::where('slug', $slug)
            ->where('is_published', true)
            ->with(['author:id,name,avatar,role', 'reviews.user:id,name,avatar', 'category:id,name'])
            ->withCount('materials')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $user = auth('sanctum')->user();
        if ($user) {
            // 🔥 CEK KEPEMILIKAN MENGGUNAKAN RELASI ITEMS 🔥
            $isPurchased = EProductPurchase::where('user_id', $user->id)
                ->whereHas('items', function($q) use ($product) {
                    $q->where('e_product_id', $product->id);
                })
                ->whereIn('status', ['PAID', 'success', 'SETTLED'])
                ->exists();
                
            $product->is_purchased = $isPurchased;
        } else {
            $product->is_purchased = false;
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function submitReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();

        // 🔥 CEK KEPEMILIKAN MENGGUNAKAN RELASI ITEMS 🔥
        $hasPurchased = EProductPurchase::where('user_id', $user->id)
            ->whereHas('items', function($q) use ($id) {
                $q->where('e_product_id', $id);
            })
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false, 
                'message' => 'Kamu harus membeli dan menyelesaikan pembayaran produk ini terlebih dahulu untuk memberikan ulasan.'
            ], 403);
        }

        $review = EProductReview::updateOrCreate(
            [
                'e_product_id' => $id,
                'user_id' => $user->id
            ],
            [
                'rating' => $request->rating,
                'review' => $request->review
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Ulasan kamu berhasil disimpan.',
            'data' => $review
        ]);
    }

    public function myProducts(Request $request)
    {
        // 🔥 AMBIL DATA DARI TABEL ORDER ITEMS KARENA STRUKTUR LAMA SUDAH DIUBAH 🔥
        $orderItems = EProductOrderItem::with([
                'product.category:id,name',
                'product.author:id,name,avatar,role', 
                'product.materials'
            ])
            ->whereHas('purchase', function($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->whereIn('status', ['PAID', 'success', 'SETTLED']);
            })
            ->latest()
            ->get();

        // Rekonstruksi struktur data agar frontend tidak error/blank
        $formattedPurchases = $orderItems->map(function($item) {
            return [
                'id'         => $item->e_product_purchase_id,
                'created_at' => $item->created_at,
                'product'    => $item->product
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedPurchases
        ]);
    }

    public function myTransactions(Request $request)
    {
        // 🔥 UPDATE RELASI: Ambil Invoice beserta daftar items (produk-produk di dalamnya) 🔥
        $transactions = EProductPurchase::with([
                'items.product', 
                'items.product.author:id,name,avatar,role', 
                'items.product.category:id,name'
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function myProductDetail(Request $request, $slug)
    {
        // 🔥 CARI PRODUK DI TABEL ORDER ITEMS YANG INVOICENYA LUNAS 🔥
        $orderItem = EProductOrderItem::with([
                'product.category:id,name',
                'product.author:id,name,avatar,role',
                'product.materials'
            ])
            ->whereHas('purchase', function($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->whereIn('status', ['PAID', 'success', 'SETTLED']);
            })
            ->whereHas('product', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })
            ->first();

        if (!$orderItem || !$orderItem->product) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum memiliki produk ini.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $orderItem->product
        ]);
    }

    /**
     * 🚀 GENERATE SIGNED URL UNTUK DOWNLOAD CEPAT 🚀
     * Frontend memanggil ini dulu untuk mendapatkan link download sementara.
     */
    public function getDownloadUrl(Request $request, $id)
    {
        $material = \App\Models\EProductMaterial::findOrFail($id);

        // 🔥 CEK KEPEMILIKAN MENGGUNAKAN RELASI ITEMS 🔥
        $hasPurchased = EProductPurchase::where('user_id', $request->user()->id)
            ->whereHas('items', function($q) use ($material) {
                $q->where('e_product_id', $material->e_product_id);
            })
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$hasPurchased) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum memiliki akses ke materi ini.'], 403);
        }

        $filePath = storage_path('app/public/' . $material->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di server.'], 404);
        }

        // Generate signed URL (berlaku 5 menit)
        $signature = hash_hmac('sha256', $id . '|' . floor(time() / 300), config('app.key'));

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileName = $material->title . '.' . $ext;

        return response()->json([
            'success' => true,
            'url' => url("/api/e-product-materials/{$id}/direct-download?signature={$signature}"),
            'filename' => $fileName
        ]);
    }

    /**
     * 🔥 DIRECT DOWNLOAD MENGGUNAKAN SIGNED URL (TANPA AUTH HEADER) 🔥
     * Browser langsung download tanpa perlu Authorization header.
     */
    public function directDownload(Request $request, $id)
    {
        $signature = $request->query('signature');

        if (!$signature) {
            return response()->json(['success' => false, 'message' => 'Link download tidak valid.'], 403);
        }

        // Verifikasi signature (berlaku 5 menit)
        $expectedSignature = hash_hmac('sha256', $id . '|' . floor(time() / 300), config('app.key'));

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['success' => false, 'message' => 'Link download sudah kedaluwarsa. Silakan coba lagi.'], 403);
        }

        $material = \App\Models\EProductMaterial::findOrFail($id);
        $filePath = storage_path('app/public/' . $material->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di server.'], 404);
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileName = $material->title . '.' . $ext;

        // 🔥 Tampilkan file secara inline (di tab baru) KHUSUS untuk HTML dengan pengamanan Sandbox
        if (in_array($ext, ['html', 'htm'])) {
            return response()->file($filePath, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Content-Security-Policy' => "sandbox allow-scripts"
            ]);
        }

        // Untuk file selain HTML, paksa browser melakukan unduhan (attachment)
        return response()->streamDownload(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192); // Stream 8KB per chunk
                flush();
            }
            fclose($stream);
        }, $fileName, [
            'Content-Type' => mime_content_type($filePath),
            'Content-Length' => filesize($filePath),
        ]);
    }

    /**
     * 🔥 UNDUH MATERI (FALLBACK - LEGACY) 🔥
     */
    public function downloadMaterial(Request $request, $id)
    {
        $material = \App\Models\EProductMaterial::findOrFail($id);

        // 🔥 CEK KEPEMILIKAN MENGGUNAKAN RELASI ITEMS 🔥
        $hasPurchased = EProductPurchase::where('user_id', $request->user()->id)
            ->whereHas('items', function($q) use ($material) {
                $q->where('e_product_id', $material->e_product_id);
            })
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$hasPurchased) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum memiliki akses ke materi ini.'], 403);
        }

        $filePath = storage_path('app/public/' . $material->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di server.'], 404);
        }

        return response()->download($filePath, $material->title . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
    }
}