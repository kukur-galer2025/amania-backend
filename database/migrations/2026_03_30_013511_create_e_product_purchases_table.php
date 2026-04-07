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
            $table->string('invoice_code')->unique(); // Cth: INV-EP-12345
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Pembeli
            $table->foreignId('e_product_id')->constrained('e_products')->onDelete('cascade'); // Produk
            
            $table->integer('amount'); // Harga total saat dibeli
            
            // 🔥 KOLOM KHUSUS PAYMENT GATEWAY (MIDTRANS) 🔥
            $table->string('snap_token')->nullable(); // Token untuk memunculkan popup pembayaran
            $table->string('payment_url')->nullable(); // Link bayar alternatif jika popup gagal
            
            // Status standar Midtrans
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_product_purchases');
    }
};