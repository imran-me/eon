<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

/* ============================================================
   GET|POST /server/api/tts.php?text=…&lang=bn   → audio/mpeg

   EON's own voice for languages the machine cannot pronounce.
   The browser plays what comes back; no OS voice, no language
   pack and no administrator is involved.

     ?status=1   → what provider is live and how much is cached
   ============================================================ */
Http::run(function () {
    Http::auth();

    if (isset($_GET['status'])) {
        Http::json(['ok' => true] + Tts::status());
        return;
    }

    $b = Http::method() === 'POST' ? Http::body() : [];
    $text = (string) ($b['text'] ?? $_GET['text'] ?? '');
    $lang = (string) ($b['lang'] ?? $_GET['lang'] ?? 'bn');
    if (trim($text) === '') Http::fail(400, 'text is required');

    $r = Tts::speak($text, $lang);
    if (!($r['ok'] ?? false)) {
        Http::fail(503, (string) ($r['error'] ?? 'speech is unavailable'));
    }

    $bytes = $r['data'] ?? (isset($r['path']) && is_file((string) $r['path']) ? file_get_contents((string) $r['path']) : null);
    if ($bytes === null || $bytes === false) Http::fail(503, 'the audio could not be read back');

    // the same sentence always sounds the same, so let the browser keep it
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen((string) $bytes));
    header('Cache-Control: public, max-age=86400');
    header('X-EON-TTS-Provider: ' . (string) ($r['provider'] ?? '?'));
    header('X-EON-TTS-Cached: ' . (($r['cached'] ?? false) ? '1' : '0'));
    echo $bytes;
});
