<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom label/kategori & posisi kanban ke tabel tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('label_name')->nullable()->after('status');
            $table->string('label_color')->nullable()->after('label_name');
            $table->integer('position')->default(0)->after('label_color');
        });

        // Tabel subtasks
        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // Tabel komentar task
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_subtasks');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['label_name', 'label_color', 'position']);
        });
    }
};
