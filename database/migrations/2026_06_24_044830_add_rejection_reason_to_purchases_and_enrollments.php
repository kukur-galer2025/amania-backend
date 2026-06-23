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
            $table->text('rejection_reason')->nullable()->after('status');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('e_product_purchases', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
