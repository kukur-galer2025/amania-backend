<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Pembuat (Admin)
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->integer('price')->default(0); // 0 berarti gratis
            $table->string('cover_image')->nullable(); // Gambar cover produk
            $table->string('file_path'); // File rahasia yang diunduh setelah bayar (PDF/ZIP)
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_products');
    }
};