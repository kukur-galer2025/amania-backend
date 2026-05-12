<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('e_product_order_items', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel Invoice (Struk Pembayaran)
            $table->foreignId('e_product_purchase_id')->constrained('e_product_purchases')->cascadeOnDelete();
            // Relasi ke tabel Produk yang dibeli
            $table->foreignId('e_product_id')->constrained('e_products')->cascadeOnDelete();
            // Harga saat barang tersebut dibeli
            $table->decimal('price', 15, 2); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_product_order_items');
    }
};