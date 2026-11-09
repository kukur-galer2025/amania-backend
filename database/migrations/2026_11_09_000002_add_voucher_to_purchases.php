<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('amount');
            $table->integer('discount_amount')->default(0)->after('voucher_code');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('amount');
            $table->integer('discount_amount')->default(0)->after('voucher_code');
        });
    }

    public function down(): void
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'discount_amount']);
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'discount_amount']);
        });
    }
};
