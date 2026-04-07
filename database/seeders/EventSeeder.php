<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['title' => 'Mastering Laravel 11 & Inertia.js', 'cat' => 'Web', 'price' => 150000],
            ['title' => 'Flutter UI/UX Masterclass', 'cat' => 'Mobile', 'price' => 75000],
            ['title' => 'Cyber Security: Web Vulnerability Basic', 'cat' => 'Security', 'price' => 120000],
            ['title' => 'Google Project Management Fundamentals', 'cat' => 'PM', 'price' => 90000],
            ['title' => 'Python for Data Science & Automation', 'cat' => 'Python', 'price' => 100000],
            ['title' => 'Cisco CCNA Networking Bootcamp', 'cat' => 'Network', 'price' => 200000],
            ['title' => 'Building RESTful API with Laravel', 'cat' => 'Web', 'price' => 85000],
            ['title' => 'State Management di Flutter (Provider & Riverpod)', 'cat' => 'Mobile', 'price' => 95000],
            ['title' => 'Pengenalan Penetration Testing (Ethical Hacking)', 'cat' => 'Security', 'price' => 150000],
            ['title' => 'Agile Framework & Scrum Master Basics', 'cat' => 'PM', 'price' => 110000],
        ];

        foreach ($events as $index => $event) {
            Event::create([
                'title' => $event['title'],
                'slug' => Str::slug($event['title']),
                'description' => 'Pelatihan intensif berfokus pada studi kasus nyata industri untuk topik ' . $event['title'] . '.',
                'venue' => $index % 2 == 0 ? 'Zoom Meeting' : 'Google Meet',
                'start_time' => Carbon::now()->addDays(($index + 1) * 3)->setTime(19, 0),
                'end_time' => Carbon::now()->addDays(($index + 1) * 3)->setTime(21, 0),
                'quota' => 100,
                'basic_price' => 0, // Semua ada opsi gratis
                'premium_price' => $event['price'],
                'certificate_link' => 'https://amania.id/certs/event-' . ($index + 1),
                'certificate_tier' => 'premium',
                'image' => 'events/sample-event-' . ($index % 3 + 1) . '.jpg',
                'user_id' => 1, // 🔥 DITAMBAHKAN: Mengaitkan event ke Organizer/Admin (User ID 1)
            ]);
        }
    }
}