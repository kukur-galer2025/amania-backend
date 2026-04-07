<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('events', function (Blueprint $table) {
        // Default 'all' berarti Basic & VIP bisa akses sertifikat
        $table->string('certificate_tier')->default('all')->after('certificate_link');
    });
}

public function down()
{
    Schema::table('events', function (Blueprint $table) {
        $table->dropColumn('certificate_tier');
    });
}
};
