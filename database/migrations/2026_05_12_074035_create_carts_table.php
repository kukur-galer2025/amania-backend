<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Milik siapa keranjang ini
            $table->foreignId('e_product_id')->constrained()->cascadeOnDelete(); // Produk apa yang dimasukkan
            $table->timestamps();

            // Memastikan 1 user cuma bisa masukin 1 produk yang sama 1 kali ke keranjang
            $table->unique(['user_id', 'e_product_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};