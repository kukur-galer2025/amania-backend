<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PINDAHKAN DATA LAMA
        // Ambil semua data pembelian lama yang e_product_id-nya tidak kosong
        $oldPurchases = DB::table('e_product_purchases')
            ->whereNotNull('e_product_id')
            ->get();

        foreach ($oldPurchases as $purchase) {
            // Pastikan tidak ada duplikasi data
            $exists = DB::table('e_product_order_items')
                ->where('e_product_purchase_id', $purchase->id)
                ->where('e_product_id', $purchase->e_product_id)
                ->exists();

            if (!$exists) {
                DB::table('e_product_order_items')->insert([
                    'e_product_purchase_id' => $purchase->id,
                    'e_product_id'          => $purchase->e_product_id,
                    'price'                 => $purchase->amount, // Harga diambil dari total amount lama
                    'created_at'            => $purchase->created_at,
                    'updated_at'            => $purchase->updated_at,
                ]);
            }
        }

        // 2. DROP KOLOM DENGAN AMAN
        Schema::table('e_product_purchases', function (Blueprint $table) {
            // Karena di screenshot terlihat e_product_id adalah Foreign Key (ada logo kunci),
            // kita WAJIB mendrop foreign key-nya terlebih dahulu sebelum mendrop kolomnya.
            // Sesuaikan nama foreign key-nya jika error, biasanya format default Laravel: tabel_kolom_foreign
            $table->dropForeign(['e_product_id']); 
            
            $table->dropColumn('e_product_id');
        });
    }

    public function down(): void
    {
        // Jika migrasi di-rollback, kembalikan kolomnya
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->foreignId('e_product_id')->nullable()->constrained('e_products');
        });
    }
};