<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_question_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('main_category', ['twk', 'tiu', 'tkp']);
            $table->string('name'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_question_sub_categories');
    }
};