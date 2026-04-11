<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_tryout_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skd_tryout_attempt_id')->constrained('skd_tryout_attempts')->cascadeOnDelete();
            $table->foreignId('skd_question_id')->constrained('skd_questions')->cascadeOnDelete();
            
            $table->enum('user_answer', ['a', 'b', 'c', 'd', 'e'])->nullable();
            $table->integer('score_obtained')->default(0);
            $table->boolean('is_doubtful')->default(false); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_tryout_answers');
    }
};