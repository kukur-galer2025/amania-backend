<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('youtube_url');
            $table->string('video_disk')->default('public')->after('video_path');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('file_path');
        });

        Schema::table('e_product_materials', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_disk']);
        });
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
        Schema::table('e_product_materials', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
    }
};
