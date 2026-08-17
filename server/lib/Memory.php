<?php
declare(strict_types=1);

/* ============================================================
   Memory — EON's own persistence: a Firestore-shaped document
   store (what the browser brain uses), conversations + messages,
   decisions log, actions/approvals log. MySQL tables (eon_*) when
   a database is configured, JSON files under storage/data otherwise
   — so it works on day one and upgrades in place.
   ============================================================ */
final class Memory
{
    private static ?bool $useDb = null;

    private static function db(): ?PDO
    {
        if (self::$useDb === null) { try { $p = Db::eon(); $p->query('SELECT 1 FROM eon_settings LIMIT 1'); self::$useDb = true; } catch (Throwable $e) { self::$useDb = false; } }
        return self::$useDb ? Db::eon() : null;
    }
    private static function file(string $name): string { return EON_ROOT . '/storage/data/' . preg_replace('/[^a-z0-9\-_.]/i', '_', $name) . '.json'; }
    private static function readJson(string $name, mixed $default = []): mixed { $f = self::file($name); if (!is_file($f)) return $default; $d = json_decode((string) file_get_contents($f), true); return $d === null ? $default : $d; }
    private static function writeJson(string $name, mixed $data): void { @file_put_contents(self::file($name), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX); }
    private static function deepMerge(array $dst, array $src): array { foreach ($src as $k => $v) { if (is_array($v) && isset($dst[$k]) && is_array($dst[$k]) && !array_is_list($v)) $dst[$k] = self::deepMerge($dst[$k], $v); else $dst[$k] = $v; } return $dst; }

    // ---- document store (col/doc) ----
    public static function docGet(string $key): ?array
    {
        if ($pdo = self::db()) { $r = Db::one($pdo, 'SELECT data FROM eon_docs WHERE doc_key = ?', [$key]); return $r ? json_decode((string) $r['data'], true) : null; }
        $all = self::readJson('docs'); return $all[$key] ?? null;
    }
    public static function docPut(string $key, array $data, bool $merge = false): array
    {
        $cur = $merge ? (self::docGet($key) ?? []) : []; $next = $merge ? self::deepMerge($cur, $data) : $data;
        if ($pdo = self::db()) { $pdo->prepare('INSERT INTO eon_docs (doc_key, data, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()')->execute([$key, json_encode($next, JSON_UNESCAPED_UNICODE)]); return $next; }
        $all = self::readJson('docs'); $all[$key] = $next; self::writeJson('docs', $all); return $next;
    }

    // ---- conversations ----
    public static function conversation(?string $id, string $channel = 'text'): array
    {
        if ($id) { if ($pdo = self::db()) { $c = Db::one($pdo, 'SELECT * FROM eon_conversations WHERE id = ?', [$id]); if ($c) return $c; } else { $all = self::readJson('conversations'); if (isset($all[$id])) return $all[$id]; } }
        $id = $id ?: bin2hex(random_bytes(8)); $c = ['id' => $id, 'channel' => $channel, 'started_at' => date('c'), 'title' => null];
        if ($pdo = self::db()) $pdo->prepare('INSERT IGNORE INTO eon_conversations (id, channel, started_at) VALUES (?, ?, NOW())')->execute([$id, $channel]);
        else { $all = self::readJson('conversations'); $all[$id] = $c; self::writeJson('conversations', $all); }
        return $c;
    }
    public static function addMessage(string $conv, string $role, string $text, array $meta = []): void
    {
        if ($pdo = self::db()) { $pdo->prepare('INSERT INTO eon_messages (conversation_id, role, text, meta, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$conv, $role, $text, json_encode($meta, JSON_UNESCAPED_UNICODE)]); return; }
        $all = self::readJson('messages'); $all[$conv][] = ['role' => $role, 'text' => $text, 'meta' => $meta, 'at' => date('c')]; $all[$conv] = array_slice($all[$conv], -200); self::writeJson('messages', $all);
    }
    /** last N turns as Anthropic-shaped messages */
    public static function history(string $conv, int $limit = 12): array
    {
        if ($pdo = self::db()) { $rows = array_reverse(Db::rows($pdo, 'SELECT role, text FROM eon_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . (int) $limit, [$conv])); }
        else { $all = self::readJson('messages'); $rows = array_slice($all[$conv] ?? [], -$limit); }
        $out = []; foreach ($rows as $r) { if (!in_array($r['role'], ['user', 'assistant'], true) || trim((string) $r['text']) === '') continue; if ($out && end($out)['role'] === $r['role']) { $out[count($out) - 1]['content'] .= "\n" . $r['text']; continue; } $out[] = ['role' => $r['role'], 'content' => (string) $r['text']]; }
        while ($out && $out[0]['role'] !== 'user') array_shift($out);
        return $out;
    }
    public static function conversations(int $limit = 30): array
    {
        if ($pdo = self::db()) return Db::rows($pdo, 'SELECT c.*, (SELECT text FROM eon_messages m WHERE m.conversation_id = c.id AND m.role = "user" ORDER BY m.id LIMIT 1) AS first_question FROM eon_conversations c ORDER BY started_at DESC LIMIT ' . (int) $limit);
        $all = array_values(self::readJson('conversations')); usort($all, fn($a, $b) => strcmp($b['started_at'] ?? '', $a['started_at'] ?? '')); return array_slice($all, 0, $limit);
    }

    // ---- decisions & actions ----
    public static function logDecisions(array $decisions, ?int $company = null): void
    {
        $day = date('Y-m-d');
        if ($pdo = self::db()) { $pdo->prepare('INSERT INTO eon_decisions (day, company_id, payload, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE payload = VALUES(payload), created_at = NOW()')->execute([$day, $company, json_encode($decisions, JSON_UNESCAPED_UNICODE)]); return; }
        $all = self::readJson('decisions'); $all[$day . ':' . ($company ?? 'all')] = ['day' => $day, 'company_id' => $company, 'payload' => $decisions, 'at' => date('c')]; self::writeJson('decisions', array_slice($all, -60, null, true));
    }
    public static function decisionsOn(string $day, ?int $company = null): ?array
    {
        if ($pdo = self::db()) { $r = Db::one($pdo, 'SELECT payload FROM eon_decisions WHERE day = ? AND (company_id <=> ?)', [$day, $company]); return $r ? json_decode((string) $r['payload'], true) : null; }
        $all = self::readJson('decisions'); return $all[$day . ':' . ($company ?? 'all')]['payload'] ?? null;
    }
    /** record an intent the boss expressed through EON (approve, remind, draft sent, note) — EON is advisory; the ERP stays the system of record */
    public static function logAction(string $kind, array $payload, string $status = 'queued', ?string $by = null): array
    {
        $a = ['id' => bin2hex(random_bytes(6)), 'kind' => $kind, 'payload' => $payload, 'status' => $status, 'by' => $by ?: Config::get('boss.name'), 'at' => date('c')];
        if ($pdo = self::db()) { $pdo->prepare('INSERT INTO eon_actions (id, kind, payload, status, actor, created_at) VALUES (?, ?, ?, ?, ?, NOW())')->execute([$a['id'], $kind, json_encode($payload, JSON_UNESCAPED_UNICODE), $status, $a['by']]); return $a; }
        $all = self::readJson('actions'); $all[] = $a; self::writeJson('actions', array_slice($all, -500)); return $a;
    }
    public static function actions(int $limit = 50): array
    {
        if ($pdo = self::db()) return array_map(fn($r) => $r + ['payload' => json_decode((string) $r['payload'], true)], Db::rows($pdo, 'SELECT * FROM eon_actions ORDER BY created_at DESC LIMIT ' . (int) $limit));
        return array_slice(array_reverse(self::readJson('actions')), 0, $limit);
    }
    public static function setting(string $key, mixed $default = null): mixed
    {
        if ($pdo = self::db()) { $r = Db::one($pdo, 'SELECT value FROM eon_settings WHERE `key` = ?', [$key]); return $r ? json_decode((string) $r['value'], true) : $default; }
        return self::readJson('settings')[$key] ?? $default;
    }
    public static function setSetting(string $key, mixed $value): void
    {
        if ($pdo = self::db()) { $pdo->prepare('INSERT INTO eon_settings (`key`, value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()')->execute([$key, json_encode($value, JSON_UNESCAPED_UNICODE)]); return; }
        $s = self::readJson('settings'); $s[$key] = $value; self::writeJson('settings', $s);
    }
    public static function backend(): string { return self::db() ? 'mysql' : 'files'; }
}
