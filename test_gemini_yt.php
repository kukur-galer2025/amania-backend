<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = env('GEMINI_API_KEY');
$prompt = "Tolong buatkan rangkuman materi dari video YouTube berikut secara detail: https://www.youtube.com/watch?v=w7ejDZ8SWv8";

$response = \Illuminate\Support\Facades\Http::withHeaders(['Content-Type' => 'application/json'])
    ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $key, [
        'contents' => [['parts' => [['text' => $prompt]]]]
    ]);

echo json_encode($response->json(), JSON_PRETTY_PRINT);
