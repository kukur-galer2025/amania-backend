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
        Schema::table('events', function (Blueprint $table) {
        $table->string('join_link')->nullable()->after('venue');
        $table->text('join_instructions')->nullable()->after('join_link');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('events', function (Blueprint $table) {
        $table->string('join_link')->nullable()->after('venue');
        $table->text('join_instructions')->nullable()->after('join_link');
    });
    }
};
