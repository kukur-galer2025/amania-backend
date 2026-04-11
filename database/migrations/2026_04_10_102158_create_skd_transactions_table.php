<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_transactions', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke User pembeli
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Relasi ke Paket Tryout yang dibeli
            $table->foreignId('skd_tryout_id')->constrained('skd_tryouts')->cascadeOnDelete();
            
            // Data Tripay
            $table->string('reference')->nullable()->unique(); // Referensi unik dari Tripay (misal: T12345...)
            $table->string('merchant_ref')->unique(); // Kode invoice buatan kita (misal: INV-SKD-...)
            $table->integer('amount'); // Total tagihan
            $table->string('payment_method')->nullable(); // Kode metode bayar (QRIS, BRIVA, dll)
            $table->string('payment_name')->nullable(); // Nama metode bayar
            $table->string('checkout_url')->nullable(); // Link ke halaman pembayaran Tripay
            
            // Status Transaksi
            $table->enum('status', ['UNPAID', 'PAID', 'EXPIRED', 'FAILED'])->default('UNPAID');
            $table->timestamp('paid_at')->nullable(); // Waktu pembayaran berhasil
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_transactions');
    }
};