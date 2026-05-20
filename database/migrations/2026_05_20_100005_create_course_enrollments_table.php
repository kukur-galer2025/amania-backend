<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('tripay_reference')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->integer('amount')->default(0);
            $table->string('checkout_url')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('expired_time')->nullable();
            $table->string('status')->default('UNPAID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
