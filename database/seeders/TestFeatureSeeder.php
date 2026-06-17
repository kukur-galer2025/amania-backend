<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;

class TestFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan ada minimal 1 course
        $course = Course::first();
        if (!$course) {
            $this->call(CourseSeeder::class);
            $course = Course::first();
        }

        if (!$course) {
            $this->command->error('Gagal membuat course percobaan.');
            return;
        }

        // 2. Berikan akses kursus ini ke semua user yang ada
        $users = User::all();
        $count = 0;
        
        foreach ($users as $user) {
            $enrollment = CourseEnrollment::firstOrCreate([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ], [
                'reference' => 'TEST-INV-' . strtoupper(uniqid()),
                'amount' => 0,
                'status' => 'PAID',
            ]);
            
            if ($enrollment->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->command->info("Berhasil memberikan akses kursus '{$course->title}' ke {$count} user.");
    }
}
