<?php
require 'vendor/autoload.php';

use MrMySQL\YoutubeTranscript\TranscriptListFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$http_client = new Client();
$request_factory = new HttpFactory();
$stream_factory = new HttpFactory();

$fetcher = new TranscriptListFetcher($http_client, $request_factory, $stream_factory);

try {
    $video_id = 'dQw4w9WgXcQ'; // Rickroll
    $transcript_list = $fetcher->fetch($video_id);
    
    $language_codes = $transcript_list->getAvailableLanguageCodes();
    $transcript = $transcript_list->findTranscript($language_codes);
    $transcript_text = $transcript->fetch();
    
    $full_text = '';
    foreach ($transcript_text as $item) {
        $full_text .= $item['text'] . ' ';
    }
    
    echo "Success! Length: " . strlen($full_text) . "\n";
    echo substr($full_text, 0, 500);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
