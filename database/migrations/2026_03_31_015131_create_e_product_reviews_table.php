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
        Schema::create('e_product_reviews', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke tabel e_products (jika produk dihapus, review ikut terhapus)
            $table->foreignId('e_product_id')->constrained('e_products')->cascadeOnDelete();
            
            // Relasi ke tabel users (pembeli yang mereview)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Rating bintang (1 sampai 5)
            $table->tinyInteger('rating')->comment('1 to 5 stars');
            
            // Teks ulasan (nullable karena kadang user cuma mau kasih bintang tanpa teks)
            $table->text('review')->nullable();
            
            $table->timestamps();
            
            // Mencegah 1 user mereview produk yang sama lebih dari 1 kali
            $table->unique(['e_product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_product_reviews');
    }
};