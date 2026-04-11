<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_questions', function (Blueprint $table) {
            $table->id();
            
            // Relasi Baru
            $table->foreignId('skd_tryout_id')->constrained('skd_tryouts')->cascadeOnDelete();
            $table->foreignId('skd_question_sub_category_id')->constrained('skd_question_sub_categories')->restrictOnDelete();
            
            $table->enum('main_category', ['twk', 'tiu', 'tkp']);
            $table->longText('question_text');
            $table->longText('option_a')->nullable();
            $table->longText('option_b')->nullable();
            $table->longText('option_c')->nullable();
            $table->longText('option_d')->nullable();
            $table->longText('option_e')->nullable();
            
            $table->integer('score_a')->default(0);
            $table->integer('score_b')->default(0);
            $table->integer('score_c')->default(0);
            $table->integer('score_d')->default(0);
            $table->integer('score_e')->default(0);
            
            $table->longText('explanation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_questions');
    }
};