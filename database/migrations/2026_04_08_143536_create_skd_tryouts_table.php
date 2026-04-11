<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skd_tryouts', function (Blueprint $table) {
            $table->id();
            
            // 🔥 RELASI KE TABEL KATEGORI (PENGGANTI ENUM) 🔥
            $table->foreignId('skd_tryout_category_id')->nullable()->constrained('skd_tryout_categories')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            
            // $table->enum('category', ['cpns', 'kedinasan'])->default('cpns'); <-- INI DIHAPUS

            $table->integer('duration_minutes')->default(100);
            
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount_price', 10, 2)->nullable(); 
            $table->dateTime('discount_start_date')->nullable();  
            $table->dateTime('discount_end_date')->nullable();    
            $table->boolean('is_hots')->default(false);           
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skd_tryouts');
    }
};