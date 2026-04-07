<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Tambahkan kolom user_id untuk mencatat siapa organizer-nya
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
       Schema::table('events', function (Blueprint $table) {
            // 🔥 PERBAIKAN: Hapus foreign key dan kolomnya jika di-rollback
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};