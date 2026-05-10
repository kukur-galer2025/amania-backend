<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            // Menambahkan kolom payment_method setelah kolom checkout_url
            $table->string('payment_method')->nullable()->after('checkout_url');
        });
    }

    public function down()
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};