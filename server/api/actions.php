<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET → recent actions, conversations, today's decisions   ·   POST {kind, payload, summary} → log an instruction from the boss */
Http::run(function () {
    Http::auth();
    if (Http::method() === 'POST') {
        $b = Http::body();
        $status = in_array($b['status'] ?? 'queued', ['queued', 'proposed', 'done', 'cancelled'], true) ? (string) $b['status'] : 'queued';
        Http::json(['ok' => true, 'action' => Memory::logAction((string) ($b['kind'] ?? 'note'), ['summary' => mb_substr((string) ($b['summary'] ?? ''), 0, 500), 'detail' => is_array($b['payload'] ?? null) ? $b['payload'] : [], 'ip' => $_SERVER['REMOTE_ADDR'] ?? null], $status)]);
    }
    Http::json(['ok' => true, 'actions' => Memory::actions((int) (Http::q('limit') ?: 50)), 'conversations' => Memory::conversations(20), 'decisions_today' => Memory::decisionsOn(date('Y-m-d'), Http::intq('company'))]);
});
