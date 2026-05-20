<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->enum('type', ['video', 'text', 'file'])->default('video')->after('title');
            $table->text('text_content')->nullable()->after('youtube_url');
            $table->string('file_path')->nullable()->after('text_content');
            $table->string('file_name')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn(['type', 'text_content', 'file_path', 'file_name']);
        });
    }
};
