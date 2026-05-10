<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('e_product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e_product_id')->constrained()->cascadeOnDelete();
            
            $table->string('title'); // Contoh: "E-Book PDF" atau "Video Tutorial"
            $table->enum('type', ['file', 'link']); 
            
            $table->string('file_path')->nullable(); // Jika upload file
            $table->string('link_url')->nullable();  // Jika pakai link (GDrive/Youtube)
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('e_product_materials');
    }
};