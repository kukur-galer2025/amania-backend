<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('registrations', function (Blueprint $table) {
    $table->id();
    $table->string('ticket_code')->unique()->nullable();
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->string('email');
    $table->string('payment_proof')->nullable();
    $table->enum('status', ['pending', 'waiting', 'verified', 'rejected'])->default('pending');
    $table->enum('tier', ['free', 'premium'])->default('free');
    $table->decimal('total_amount', 15, 2)->default(0.00); //
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
