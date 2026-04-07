<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            
            $table->string('bank_code'); // Ini akan berisi: 'bca', 'mandiri', 'bni', 'bri', dll.
            $table->string('account_number');
            $table->string('account_holder');
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_bank_accounts');
    }
};