<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_product_purchases', function (Blueprint $table) {
            $table->id();
            
            // 🔥 KOLOM REFERENSI TRIPAY 🔥
            $table->string('reference')->unique(); // Kode Referensi dari sistem kita (Merchant Ref)
            $table->string('tripay_reference')->nullable()->unique(); // Kode Referensi asli dari Tripay
            
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Pembeli
            $table->foreignId('e_product_id')->constrained('e_products')->onDelete('cascade'); // Produk
            
            $table->integer('amount'); // Harga total saat dibeli
            
            // Link redirect ke halaman pembayaran Tripay
            $table->string('checkout_url')->nullable(); 
            
            // Status standar Tripay
            // UNPAID, PAID, EXPIRED, FAILED, REFUND
            $table->string('status')->default('UNPAID'); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_product_purchases');
    }
};