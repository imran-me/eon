<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET → recent actions, conversations, today's decisions   ·   POST {kind, payload, summary} → log an instruction from the boss */
Http::run(function () {
    Http::auth();
    if (Http::method() === 'POST') {
        $b = Http::body();
        Http::json(['ok' => true, 'action' => Memory::logAction((string) ($b['kind'] ?? 'note'), ['summary' => $b['summary'] ?? '', 'detail' => $b['payload'] ?? []], (string) ($b['status'] ?? 'queued'))]);
    }
    Http::json(['ok' => true, 'actions' => Memory::actions((int) (Http::q('limit') ?: 50)), 'conversations' => Memory::conversations(20), 'decisions_today' => Memory::decisionsOn(date('Y-m-d'), Http::intq('company'))]);
});
