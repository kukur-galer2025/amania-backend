<?php
require 'vendor/autoload.php';

// I will just use regex to scrape the transcript manually.
function getYoutubeTranscript($url) {
    $html = file_get_contents($url);
    if (preg_match('/"captionTracks":\[\{"baseUrl":"(.*?)"/', $html, $matches)) {
        $transcriptUrl = str_replace('\u0026', '&', $matches[1]);
        $xml = file_get_contents($transcriptUrl);
        $xmlObj = simplexml_load_string($xml);
        $transcript = '';
        foreach ($xmlObj->text as $text) {
            $transcript .= (string)$text . ' ';
        }
        return html_entity_decode($transcript);
    }
    return false;
}

$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'; // Rickroll
$transcript = getYoutubeTranscript($url);
echo "Transcript: " . substr($transcript, 0, 500) . "\n";
