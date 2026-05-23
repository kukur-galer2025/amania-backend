<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Course;
use App\Models\CourseLesson;

foreach(Course::all() as $c){
    $c->description = str_replace('&nbsp;', ' ', $c->description);
    $c->save();
}

foreach(CourseLesson::all() as $l){
    if($l->text_content){
        $l->text_content = str_replace('&nbsp;', ' ', $l->text_content);
        $l->save();
    }
}

// Fix seeder file too
$file = __DIR__ . '/database/seeders/CourseSeeder.php';
$content = file_get_contents($file);
$content = str_replace('&nbsp;', ' ', $content);
file_put_contents($file, $content);

echo "Fixed literal &nbsp; spaces.";
