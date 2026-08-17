<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* POST { question, conversation_id?, company?, voice?, facts? }  →  { ok, mode, text, speak, tools_used, usage, conversation_id } */
Http::run(function () {
    Http::auth();
    if (Http::method() !== 'POST') Http::fail(405, 'POST a JSON body: {"question": "..."}');
    $b = Http::body();
    $q = trim((string) ($b['question'] ?? $b['q'] ?? ''));
    if ($q === '') Http::fail(400, 'question is required');
    if (mb_strlen($q) > 4000) $q = mb_substr($q, 0, 4000);
    $company = (isset($b['company']) && $b['company'] !== '' && $b['company'] !== null) ? (int) $b['company'] : null;
    $t0 = microtime(true);
    $out = Brain::ask($q, isset($b['conversation_id']) ? (string) $b['conversation_id'] : null, $company, (bool) ($b['voice'] ?? false), is_array($b['facts'] ?? null) ? $b['facts'] : [], isset($b['lang']) ? (string) $b['lang'] : null);
    $out['ok'] = true; $out['ms'] = (int) round((microtime(true) - $t0) * 1000); $out['source'] = Dataset::source();
    Http::json($out);
});
