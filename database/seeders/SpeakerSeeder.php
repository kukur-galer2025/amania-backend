<?php

namespace Database\Seeders;

use App\Models\Speaker;
use Illuminate\Database\Seeder;

class SpeakerSeeder extends Seeder
{
    public function run(): void
    {
        // Menambahkan 1 pembicara untuk masing-masing dari 10 event
        for ($i = 1; $i <= 10; $i++) {
            Speaker::create([
                'event_id' => $i,
                'name' => 'Instruktur Ahli ' . $i,
                'role' => 'Senior Tech Consultant',
                'photo' => 'speakers/sample-speaker.jpg',
            ]);
        }
    }
}