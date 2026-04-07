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
        Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique(); // Ditambahkan untuk SEO
    $table->text('description');
    $table->string('venue');
    $table->dateTime('start_time');
    $table->dateTime('end_time');
    $table->integer('quota');
    $table->integer('price')->default(0);
    $table->integer('premium_price')->nullable();
    $table->string('certificate_link')->nullable();
    $table->string('image')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
