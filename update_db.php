<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lesson = \App\Models\CourseLesson::where('title', 'Apa itu React.js dan Mengapa Menggunakannya?')->first();
if ($lesson) {
    $lesson->youtube_url = 'https://www.youtube.com/watch?v=5kHyviqjhCk';
    $lesson->save();
    echo "Updated successfully to: " . $lesson->youtube_url;
} else {
    echo "Lesson not found.";
}
