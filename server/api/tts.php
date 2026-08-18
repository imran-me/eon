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

    // Whatever reaches here may be screen text — "৳1.9 L", an account code, a URL.
    // Put it through the spoken-form builder unless the caller says it is ready,
    // so anything that asks EON to speak is spoken properly, not read out.
    // ?debug=1 — report what happens to the text instead of returning audio
    if (isset($_GET['debug'])) {
        $steps = ['in_len' => mb_strlen($text), 'in_utf8' => mb_check_encoding($text, 'UTF-8')];
        $sp = class_exists('Speech') ? Speech::spoken($text, $lang === 'bn' ? 'bn' : 'en') : $text;
        $steps['spoken'] = $sp;
        $steps['spoken_utf8'] = mb_check_encoding($sp, 'UTF-8');
        $sh = class_exists('Speech') ? Speech::shorten($sp) : $sp;
        $steps['short'] = $sh;
        $steps['short_utf8'] = mb_check_encoding($sh, 'UTF-8');
        $collapse = preg_replace('/\s+/u', ' ', $sh);
        $steps['collapse_null'] = ($collapse === null);
        $steps['preg_error'] = preg_last_error_msg();
        $steps['pcre'] = defined('PCRE_VERSION') ? PCRE_VERSION : (function_exists('preg_last_error') ? 'n/a' : '');
        Http::json(['ok' => true, 'debug' => $steps]);
        return;
    }

    $prepared = false;
    if (empty($b['raw']) && !isset($_GET['raw']) && class_exists('Speech')) {
        $before = $text;
        $text = Speech::shorten(Speech::spoken($text, $lang === 'bn' ? 'bn' : 'en'));
        if (trim($text) === '') { $text = $before; }
        $prepared = ($text !== $before);
    }

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
    header('X-EON-TTS-Prepared: ' . ($prepared ? '1' : '0'));
    echo $bytes;
});
