<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('GEMINI_API_KEY');
$response = Illuminate\Support\Facades\Http::withHeaders([
    'Content-Type' => 'application/json',
])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey, [
    'contents' => [
        ['parts' => [['text' => 'Halo']]]
    ]
]);

echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
