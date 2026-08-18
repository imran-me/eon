<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
Http::run(function () {
    $db = Config::dbEnabled() && Db::available();
    $root = dirname(EON_ROOT);
    $dep = @json_decode((string) @file_get_contents($root . '/deploy/state.json'), true) ?: [];
    // no state file (post-deploy never ran, or could not write)? read the checkout itself —
    // the commit is in .git, so the live build is always reportable.
    if (empty($dep['commit'])) {
        $head = trim((string) @file_get_contents($root . '/.git/HEAD'));
        $sha = '';
        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            $sha = trim((string) @file_get_contents($root . '/.git/' . $ref));
            if ($sha === '') {                                   // packed refs (a fresh clone keeps them packed)
                foreach (explode("\n", (string) @file_get_contents($root . '/.git/packed-refs')) as $l) {
                    if (str_ends_with(trim($l), ' ' . $ref)) { $sha = strtok(trim($l), ' '); break; }
                }
            }
        } elseif (preg_match('/^[0-9a-f]{40}$/', $head)) {
            $sha = $head;
        }
        if (preg_match('/^[0-9a-f]{7,40}$/', $sha)) {
            $dep['commit'] = substr($sha, 0, 7);
            $mt = @filemtime($root . '/.git/HEAD');
            $dep['deployed_at'] = $dep['deployed_at'] ?? ($mt ? date('c', $mt) : null);
        }
    }
    Http::json([
        'ok' => true, 'name' => 'EON server', 'version' => EON_VERSION, 'time' => date('c'), 'php' => PHP_VERSION,
        'db' => $db, 'source' => Dataset::source(), 'llm' => Config::llmEnabled(), 'llm_key' => Config::llmKeyPresent(), 'sdk' => class_exists('Anthropic\Client'),
        'memory' => Memory::backend(), 'auth' => (string) Config::get('token', '') !== '' ? 'token' : (Http::auth(false) ? 'open-demo' : 'token-required'), 'python' => Py::available(), 'model' => Config::get('anthropic.model'),
        'commit' => $dep['commit'] ?? null, 'deployed' => $dep['deployed_at'] ?? null,
    ]);
});
