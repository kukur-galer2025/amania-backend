<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_products', function (Blueprint $table) {
            // Tambahkan kolom category_id setelah user_id
            $table->foreignId('e_product_category_id')->nullable()->after('user_id')->constrained('e_product_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('e_products', function (Blueprint $table) {
            $table->dropForeign(['e_product_category_id']);
            $table->dropColumn('e_product_category_id');
        });
    }
};