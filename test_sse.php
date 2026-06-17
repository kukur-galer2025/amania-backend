<?php
$apiKey = 'AQ.Ab8RN6LzKKJbH0vtG-uB1HOrQSMqSHKrSRy99DCShPJJIGu3Uw';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:streamGenerateContent?alt=sse&key=' . $apiKey;

$payload = [
    'contents' => [
        ['parts' => [['text' => 'Tuliskan puisi pendek tentang coding (4 baris).']]]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$fullText = "";

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullText) {
    // Print data to see raw stream
    echo "CHUNK: " . $data . "\n";
    
    // Parse the SSE chunk to extract text
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $jsonStr = substr($line, 6);
            $json = json_decode($jsonStr, true);
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $fullText .= $json['candidates'][0]['content']['parts'][0]['text'];
            }
        }
    }
    
    return strlen($data);
});

curl_exec($ch);
curl_close($ch);

echo "\n\nFULL TEXT ASSEMBLED:\n$fullText\n";
