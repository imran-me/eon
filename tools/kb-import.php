#!/usr/bin/env php
<?php
declare(strict_types=1);
/* ============================================================
   tools/kb-import.php — load the generated corpus into MySQL.

   Reads tools/corpus/kb-*.jsonl and q-*.jsonl and writes them into
   eon_kb and eon_kb_questions (server/install/schema-kb.sql).

     php tools/kb-import.php                 both corpora
     php tools/kb-import.php --only kb       knowledge only
     php tools/kb-import.php --only q        questions only
     php tools/kb-import.php --truncate      empty the tables first
     php tools/kb-import.php --dry           parse and count, write nothing
     php tools/kb-import.php --batch 500     rows per INSERT (default 400)
     php tools/kb-import.php --lang en       load one language only
     php tools/kb-import.php --vertical a,b  load only these verticals

   The two filters exist because the full corpus is ~360 MB in MySQL and a
   shared-hosting database quota is a real number. Loading narrow costs
   nothing: the corpus regenerates from tools/kb/ in seconds.

   Streams line by line: the knowledge shards are 25 MB each and a
   file_get_contents of the lot would exceed the memory limit on the
   host. Uses INSERT … ON DUPLICATE KEY UPDATE, so a re-run after a
   regeneration updates in place and a half-finished import can simply
   be run again.
   ============================================================ */

require_once dirname(__DIR__) . '/server/bootstrap.php';

$argvOpts = static function (array $argv, string $name, $default = null) {
    $i = array_search($name, $argv, true);
    if ($i === false) return $default;
    return $argv[$i + 1] ?? true;
};
$has = static fn(array $argv, string $n): bool => in_array($n, $argv, true);

$ONLY     = $argvOpts($argv, '--only', null);
$TRUNCATE = $has($argv, '--truncate');
$DRY      = $has($argv, '--dry');
$BATCH    = max(50, (int) $argvOpts($argv, '--batch', 400));
$DIR      = rtrim((string) $argvOpts($argv, '--dir', __DIR__ . '/corpus'), '/\\');

$LANG  = $argvOpts($argv, '--lang', null);
$VERTS = ($vsel = $argvOpts($argv, '--vertical', null))
    ? array_values(array_filter(array_map('trim', explode(',', (string) $vsel))))
    : null;

/** does this row pass the --lang / --vertical filters? */
$keep = static function (array $r) use ($LANG, $VERTS): bool {
    if ($LANG !== null && (string) ($r['lang'] ?? 'en') !== (string) $LANG) return false;
    if ($VERTS !== null && !in_array((string) ($r['vertical'] ?? ''), $VERTS, true)) return false;
    return true;
};
if ($LANG !== null)  echo "filter: lang = {$LANG}\n";
if ($VERTS !== null) echo 'filter: vertical in ' . implode(', ', $VERTS) . "\n";

if (!is_dir($DIR)) {
    fwrite(STDERR, "no corpus at {$DIR} — run: node tools/kb/make-kb.mjs && node tools/kb/make-questions.mjs\n");
    exit(1);
}

$pdo = null;
if (!$DRY) {
    try {
        $pdo = Db::eon();
    } catch (Throwable $e) {
        fwrite(STDERR, "database not reachable: {$e->getMessage()}\n");
        fwrite(STDERR, "configure server/config.local.php, or run with --dry to check the corpus alone.\n");
        exit(1);
    }
    foreach (['eon_kb', 'eon_kb_questions'] as $t) {
        if (!Db::tableExists($pdo, $t)) {
            fwrite(STDERR, "table {$t} is missing — run server/install/schema-kb.sql first.\n");
            exit(1);
        }
    }
    if ($TRUNCATE) {
        foreach (['eon_kb', 'eon_kb_questions'] as $t) {
            if ($ONLY === 'kb' && $t !== 'eon_kb') continue;
            if ($ONLY === 'q' && $t !== 'eon_kb_questions') continue;
            $pdo->exec("TRUNCATE TABLE {$t}");
            echo "truncated {$t}\n";
        }
    }
}

/** stream one JSONL file, handing each decoded row to $fn */
function eachRow(string $file, callable $fn): array
{
    $fh = fopen($file, 'rb');
    if (!$fh) throw new RuntimeException("cannot read {$file}");
    $n = 0; $bad = 0;
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $row = json_decode($line, true);
        if (!is_array($row)) { $bad++; continue; }
        $fn($row); $n++;
    }
    fclose($fh);
    return [$n, $bad];
}

/** flush a batch of rows */
function flushBatch(?PDO $pdo, string $sql, array &$batch): void
{
    if (!$batch) return;
    if ($pdo) {
        $st = $pdo->prepare($sql . implode(',', array_fill(0, count($batch), $batch[0]['__ph'])) . $batch[0]['__upd']);
        $flat = [];
        foreach ($batch as $b) foreach ($b['v'] as $v) $flat[] = $v;
        $st->execute($flat);
    }
    $batch = [];
}

$totals = [];

/* ---------------- knowledge ---------------- */
if ($ONLY !== 'q') {
    $files = glob($DIR . '/kb-*.jsonl') ?: [];
    sort($files);
    if (!$files) fwrite(STDERR, "warning: no kb-*.jsonl in {$DIR}\n");

    $ph  = '(?,?,?,?,?,?,?,?,?,?)';
    $upd = ' ON DUPLICATE KEY UPDATE facet=VALUES(facet), lang=VALUES(lang), vertical=VALUES(vertical),'
         . ' module=VALUES(module), subject=VALUES(subject), title=VALUES(title), body=VALUES(body),'
         . ' tags=VALUES(tags), meta=VALUES(meta)';
    $sql = 'INSERT INTO eon_kb (id, facet, lang, vertical, module, subject, title, body, tags, meta) VALUES ';

    $batch = []; $count = 0; $skipped = 0;
    foreach ($files as $f) {
        [$n, $bad] = eachRow($f, function (array $r) use (&$batch, &$count, &$skipped, $pdo, $sql, $ph, $upd, $BATCH, $keep) {
            if (!$keep($r)) return;
            if (empty($r['id']) || empty($r['title']) || empty($r['text'])) { $skipped++; return; }
            /* everything the retrieval layer does not filter on rides in meta */
            $meta = array_diff_key($r, array_flip(['id','facet','lang','vertical','module','subject','title','text','tags']));
            $batch[] = ['__ph' => $ph, '__upd' => $upd, 'v' => [
                mb_substr((string) $r['id'], 0, 190),
                mb_substr((string) ($r['facet'] ?? 'unknown'), 0, 32),
                mb_substr((string) ($r['lang'] ?? 'en'), 0, 4),
                $r['vertical'] !== null ? mb_substr((string) $r['vertical'], 0, 32) : null,
                $r['module']   !== null ? mb_substr((string) $r['module'], 0, 40)   : null,
                isset($r['subject']) && $r['subject'] !== null ? mb_substr((string) $r['subject'], 0, 190) : null,
                mb_substr((string) $r['title'], 0, 400),
                (string) $r['text'],
                mb_substr(implode(',', (array) ($r['tags'] ?? [])), 0, 255),
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]];
            $count++;
            if (count($batch) >= $BATCH) flushBatch($pdo, $sql, $batch);
        });
        echo sprintf("  %-24s %7s rows%s\n", basename($f), number_format($n), $bad ? " ({$bad} unparsable)" : '');
    }
    flushBatch($pdo, $sql, $batch);
    $totals['eon_kb'] = $count;
    if ($skipped) echo "  skipped {$skipped} incomplete records\n";
}

/* ---------------- questions ---------------- */
if ($ONLY !== 'kb') {
    $files = glob($DIR . '/q-*.jsonl') ?: [];
    sort($files);
    if (!$files) fwrite(STDERR, "warning: no q-*.jsonl in {$DIR}\n");

    $ph  = '(?,?,?,?,?,?,?,?,?,?)';
    $upd = ' ON DUPLICATE KEY UPDATE q=VALUES(q), lang=VALUES(lang), expect_lang=VALUES(expect_lang),'
         . ' vertical=VALUES(vertical), module=VALUES(module), intent=VALUES(intent), facet=VALUES(facet),'
         . ' answer_facet=VALUES(answer_facet), subject=VALUES(subject)';
    $sql = 'INSERT INTO eon_kb_questions (id, q, lang, expect_lang, vertical, module, intent, facet, answer_facet, subject) VALUES ';

    $batch = []; $count = 0; $skipped = 0;
    foreach ($files as $f) {
        [$n, $bad] = eachRow($f, function (array $r) use (&$batch, &$count, &$skipped, $pdo, $sql, $ph, $upd, $BATCH, $keep) {
            if (!$keep($r)) return;
            if (empty($r['id']) || empty($r['q'])) { $skipped++; return; }
            $batch[] = ['__ph' => $ph, '__upd' => $upd, 'v' => [
                mb_substr((string) $r['id'], 0, 32),
                mb_substr((string) $r['q'], 0, 400),
                mb_substr((string) ($r['lang'] ?? 'en'), 0, 4),
                mb_substr((string) ($r['expect_lang'] ?? 'en'), 0, 4),
                isset($r['vertical']) && $r['vertical'] !== null ? mb_substr((string) $r['vertical'], 0, 32) : null,
                isset($r['module'])   && $r['module']   !== null ? mb_substr((string) $r['module'], 0, 40)   : null,
                mb_substr((string) ($r['intent'] ?? 'unknown'), 0, 32),
                mb_substr((string) ($r['facet'] ?? 'unknown'), 0, 32),
                mb_substr((string) ($r['answer_facet'] ?? 'unknown'), 0, 32),
                isset($r['subject']) && $r['subject'] !== null ? mb_substr((string) $r['subject'], 0, 190) : null,
            ]];
            $count++;
            if (count($batch) >= $BATCH) flushBatch($pdo, $sql, $batch);
        });
        echo sprintf("  %-24s %7s rows%s\n", basename($f), number_format($n), $bad ? " ({$bad} unparsable)" : '');
    }
    flushBatch($pdo, $sql, $batch);
    $totals['eon_kb_questions'] = $count;
    if ($skipped) echo "  skipped {$skipped} incomplete questions\n";
}

/* ---------------- report ---------------- */
echo "\n";
foreach ($totals as $table => $n) {
    echo sprintf("%-18s %9s rows %s\n", $table, number_format($n), $DRY ? 'parsed (nothing written)' : 'written');
}
if (!$DRY && $pdo) {
    foreach (array_keys($totals) as $table) {
        $have = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo sprintf("%-18s %9s rows now in the table\n", $table, number_format($have));
    }
}
