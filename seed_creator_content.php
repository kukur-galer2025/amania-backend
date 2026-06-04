<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'siswa1@gmail.com')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}

$course = clone \App\Models\Course::first();
if($course) {
    $course = $course->replicate();
    $course->title = 'Kursus Kreator 1';
    $course->slug = 'kursus-kreator-1-' . time();
    $course->user_id = $user->id; // FIX
    $course->is_published = true;
    $course->save();
}

$eproduct = clone \App\Models\EProduct::first();
if($eproduct) {
    $eproduct = $eproduct->replicate();
    $eproduct->title = 'Produk Kreator 1';
    $eproduct->slug = 'produk-kreator-1-' . time();
    $eproduct->user_id = $user->id; // FIX
    $eproduct->is_published = true;
    $eproduct->save();
}

echo "Created course and e-product for creator ID {$user->id}!\n";
