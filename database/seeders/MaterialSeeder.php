<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        // 10 Materi YouTube, dihubungkan ke 10 Event
        for ($i = 1; $i <= 10; $i++) {
            Material::create([
                'event_id' => $i,
                'title' => 'Video Record: Sesi Pembelajaran Modul ' . $i,
                'type' => 'video',
                'access_tier' => 'premium',
                'file_path' => null, // Dikosongkan sesuai permintaan
                'link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // Link YT Dummy
            ]);
        }
    }
}