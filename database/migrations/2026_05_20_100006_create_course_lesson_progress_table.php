<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_lesson_id')->constrained()->onDelete('cascade');
            $table->boolean('is_completed')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'course_lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_progress');
    }
};
