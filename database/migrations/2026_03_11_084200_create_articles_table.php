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
        Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('category')->default('Umum');
    $table->string('image')->nullable();
    $table->longText('content');
    $table->integer('read_time')->default(5);
    // FIX: Gunakan tinyInteger, bukan tinyint
    $table->tinyInteger('is_published')->default(1); 
    $table->timestamps();
});
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
