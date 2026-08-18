<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
Http::run(function () {
    $db = Config::dbEnabled() && Db::available();
    $dep = @json_decode((string) @file_get_contents(dirname(EON_ROOT) . '/deploy/state.json'), true) ?: [];
    Http::json([
        'ok' => true, 'name' => 'EON server', 'version' => EON_VERSION, 'time' => date('c'), 'php' => PHP_VERSION,
        'db' => $db, 'source' => Dataset::source(), 'llm' => Config::llmEnabled(), 'llm_key' => Config::llmKeyPresent(), 'sdk' => class_exists('Anthropic\Client'),
        'memory' => Memory::backend(), 'auth' => (string) Config::get('token', '') !== '' ? 'token' : (Http::auth(false) ? 'open-demo' : 'token-required'), 'python' => Py::available(), 'model' => Config::get('anthropic.model'),
        'commit' => $dep['commit'] ?? null, 'deployed' => $dep['deployed_at'] ?? null,
    ]);
});
