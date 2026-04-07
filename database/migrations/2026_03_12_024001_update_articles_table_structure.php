<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // 1. Hapus kolom category lama yang berbentuk string
            if (Schema::hasColumn('articles', 'category')) {
                $table->dropColumn('category');
            }

            // 2. Tambah kolom relasi ke kategori artikel (Foreign Key)
            $table->foreignId('article_category_id')
                  ->after('slug')
                  ->constrained('article_categories')
                  ->onDelete('cascade');

            // 3. Tambah kolom relasi ke user/admin yang menulis
            // (Ini yang bikin error tadi karena sudah ada di sini)
            $table->foreignId('user_id')
                  ->after('is_published')
                  ->constrained('users')
                  ->onDelete('cascade');

            // 🔥 4. TAMBAHAN BARU: Ubah default is_published menjadi 0 (Draft/Pending)
            // agar artikel Organizer tidak langsung tayang sebelum di-ACC Superadmin
            $table->tinyInteger('is_published')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Kembalikan seperti semula jika di-rollback
            $table->dropForeign(['article_category_id']);
            $table->dropColumn('article_category_id');
            
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');

            $table->string('category')->default('Umum')->after('slug');
        });
    }
};