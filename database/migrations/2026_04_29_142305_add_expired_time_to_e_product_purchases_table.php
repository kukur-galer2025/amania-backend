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
        Schema::table('e_product_purchases', function (Blueprint $table) {
            // Menggunakan unsignedBigInteger agar aman selamanya
            $table->unsignedBigInteger('expired_time')->nullable()->after('checkout_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->dropColumn('expired_time');
        });
    }
};