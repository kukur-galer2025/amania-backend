<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_tryout_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skd_tryout_id')->constrained('skd_tryouts')->cascadeOnDelete();
            
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            
            $table->integer('score_twk')->default(0);
            $table->integer('score_tiu')->default(0);
            $table->integer('score_tkp')->default(0);
            $table->integer('total_score')->default(0);
            $table->boolean('is_passed')->default(false); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_tryout_attempts');
    }
};