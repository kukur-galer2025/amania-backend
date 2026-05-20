<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah deskripsi ke course_categories
        Schema::table('course_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
        });

        // 2. Buat tabel course_reviews (rating)
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']); // 1 review per user per course
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');

        Schema::table('course_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
