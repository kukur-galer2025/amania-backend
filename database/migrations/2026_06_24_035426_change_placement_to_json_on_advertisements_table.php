<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Convert existing string values to JSON array format
        $ads = DB::table('advertisements')->get();
        foreach ($ads as $ad) {
            $currentPlacement = $ad->placement ?: 'beranda';
            // If it's already JSON, skip
            if (str_starts_with($currentPlacement, '[')) continue;
            DB::table('advertisements')
                ->where('id', $ad->id)
                ->update(['placement' => json_encode([$currentPlacement])]);
        }

        // 2. Change column type to JSON (no default for MySQL JSON columns)
        Schema::table('advertisements', function (Blueprint $table) {
            $table->json('placement')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert JSON arrays back to single strings first
        $ads = DB::table('advertisements')->get();
        foreach ($ads as $ad) {
            $decoded = json_decode($ad->placement, true);
            $single = is_array($decoded) ? ($decoded[0] ?? 'beranda') : $ad->placement;
            DB::table('advertisements')
                ->where('id', $ad->id)
                ->update(['placement' => $single]);
        }

        Schema::table('advertisements', function (Blueprint $table) {
            $table->string('placement')->default('beranda')->change();
        });
    }
};
