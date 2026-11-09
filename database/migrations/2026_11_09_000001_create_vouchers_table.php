<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->integer('discount_value');
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->integer('min_purchase')->default(0);
            $table->boolean('is_active')->default(true);
            $table->enum('applicable_to', ['all', 'e_product', 'course', 'event'])->default('all');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
