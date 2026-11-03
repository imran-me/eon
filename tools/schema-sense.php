<?php
/* ============================================================
   EON · schema-sense — read the ERP's database the way an analyst would.

   The ERP map (erp-map.mjs) knows the screens. This knows the DATA:
   every table and every column, and for each column what it *is* —
   not just its SQL type but its role in the business:

     money      decimal columns named amount/price/cost/fee/due/…
     date       date/datetime/timestamp, with the range actually used
     status     a short vocabulary (status/stage/type/kind/method/…),
                with every value the data really holds and how often
     flag       tinyint(1), is_/has_ columns
     key        the primary key; reference: a *_id and the table it points at
     label      name/title/subject — what a person calls a row
     contact    phone/email; identity: passport/nid
     secret     password/token/key — named, never read
     text       notes, remarks, descriptions; json

   Plus each table's row count and the date range of its business date.
   Personal data is never written out: names, phones, passports and free
   text are described by their role and fill rate only. The status
   vocabularies are the ERP's own words (pending, approved, refund…).

     php tools/schema-sense.php [--out server/kb/erp-schema.json]

   Run against the real database (EON_DB_HOST=127.0.0.1 locally).
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/../server/bootstrap.php';

$opt = getopt('', ['out::']);
$out = $opt['out'] ?? (__DIR__ . '/../server/kb/erp-schema.json');
$pdo = Db::erp();
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$cols = Db::rows($pdo, "SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE dt, COLUMN_TYPE ct, IS_NULLABLE n, COLUMN_KEY k, COLUMN_COMMENT cm
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION", [$db]);
$fks = Db::rows($pdo, "SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$db]);
$fkMap = [];
foreach ($fks as $f) $fkMap[$f['t'] . '.' . $f['c']] = $f['rt'];

/* The ERP's own models say what a column points at — belongsTo(Bank::class,
   'account_id') — which beats both a missing foreign key and a column's name
   (transactions.account_id is a bank, not a chart-of-accounts row). */
$modelMap = [];
(function () use (&$modelMap) {
    $erp = __DIR__ . '/../erp';
    $files = array_merge(glob("$erp/app/Models/*.php") ?: [], glob("$erp/Modules/*/Models/*.php") ?: [], glob("$erp/Modules/*/*/*/Models/*.php") ?: [], glob("$erp/Modules/*/*/Models/*.php") ?: []);
    $tableOf = [];
    $src = [];
    foreach ($files as $p) {
        $code = (string) file_get_contents($p);
        $cls = basename($p, '.php');
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cls));
        $plural = preg_match('/y$/', $snake) && !preg_match('/[aeiou]y$/', $snake) ? substr($snake, 0, -1) . 'ies' : (preg_match('/(s|x|ch|sh)$/', $snake) ? $snake . 'es' : $snake . 's');
        $tableOf[dirname($p)][$cls] = preg_match("/protected\\s+\\\$table\\s*=\\s*['\"]([a-z0-9_]+)['\"]/", $code, $m) ? $m[1] : $plural;
        $src[$p] = $code;
    }
    $main = "$erp/app/Models";
    foreach ($src as $p => $code) {
        $cls = basename($p, '.php'); $dir = dirname($p);
        preg_match_all("/belongsTo\\(\\s*\\\\?(?:[A-Za-z]+\\\\)*([A-Za-z0-9]+)::class\\s*(?:,\\s*['\"]([a-z0-9_]+)['\"])?/", $code, $mm, PREG_SET_ORDER);
        foreach ($mm as $m) {
            /* the model beside this one first (WoodArt's Project is wa_projects), then the ERP's own */
            $target = $tableOf[$dir][$m[1]] ?? $tableOf[$main][$m[1]] ?? null;
            if (!$target) foreach ($tableOf as $byDir) if (isset($byDir[$m[1]])) { $target = $byDir[$m[1]]; break; }
            if (!$target) continue;
            $col = $m[2] ?? '';
            if ($col === '') $col = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $m[1])) . '_id';
            $modelMap[$tableOf[$dir][$cls] . '.' . $col] = $target;
        }
    }
})();
$tables = [];
foreach ($cols as $c) $tables[$c['t']][] = $c;
$names = array_keys($tables);
$colNames = [];
foreach ($tables as $t => $l) $colNames[$t] = array_column($l, 'c');

/* EON's own tables, Laravel's plumbing and caches are not the business */
$skip = '/^(correction_backup_|eon_|migrations$|password_reset|sessions$|cache|jobs$|job_batches$|failed_jobs$|personal_access_tokens$|telescope_)/';

function plural(string $w): array {
    return array_unique([$w . 's', $w . 'es', preg_replace('/y$/', 'ies', $w), $w]);
}
/** where a *_id column points: the declared foreign key, else the table its name implies */
function target(string $table, string $col, array $fkMap, array $names): ?string {
    if (isset($GLOBALS['modelMap']["$table.$col"])) return $GLOBALS['modelMap']["$table.$col"];
    if (isset($fkMap["$table.$col"])) return $fkMap["$table.$col"];
    if (!preg_match('/^(.*)_id$/', $col, $m)) return null;
    $base = $m[1];
    $alias = ['client' => 'users', 'vendor' => 'users', 'agent' => 'users', 'party' => 'users', 'user' => 'users', 'employee' => 'users',
        'created_by' => 'users', 'updated_by' => 'users', 'assigned_officer' => 'users', 'beneficiary_party' => 'users', 'buyer' => 'users',
        'boarding_officer' => 'users', 'immigration_officer' => 'users', 'cost_bank' => 'banks', 'payment_instruction_bank' => 'banks',
        'validating_carrier' => 'airlines', 'sender' => 'users', 'new_vendor' => 'users', 'reversed_journal_entry' => 'journal_entries', 'reversal_journal_entry' => 'journal_entries', 'from_airport' => 'airports', 'to_airport' => 'airports'];
    if (isset($alias[$base])) return $alias[$base];
    foreach (plural($base) as $p) if (in_array($p, $names, true)) return $p;
    // ticket_purchase_id → ticket_purchases; contract_flight_passenger_id → contract_flight_passengers
    $last = preg_replace('/^.*_/', '', $base);
    foreach ($names as $n) if (preg_match('/(^|_)' . preg_quote($base, '/') . 's?$/', $n)) return $n;
    foreach (plural($last) as $p) if (in_array($p, $names, true)) return $p;
    return null;
}

const MONEY = '/(amount|price|cost|fee|fees|total|paid|due|balance|salary|debit|credit|fare|commission|charge|charges|penalty|refund|tax|vat|rate|value|budget|advance|deduction|bonus|allowance|gross|net|payable|receivable|revenue|expense|profit|loan|interest|installment|instalment)/';
const STATUSY = '/(^|_)(status|stage|type|kind|method|mode|flag|category|class|source|state|bearer|side|direction|priority|role|level|channel|event|fault|handling|trip|currency|gender|frequency|period|unit|basis|nature)($|_)/';

function role(array $c, string $table, array $fkMap, array $names): array {
    $n = $c['c']; $dt = strtolower($c['dt']); $ct = strtolower($c['ct']);
    if ($c['k'] === 'PRI') return ['role' => 'key'];
    if (preg_match('/(password|secret|token|api_key|remember_token|two_factor|otp|pin_code)/', $n)) return ['role' => 'secret'];
    if (preg_match('/_id$/', $n) && preg_match('/int/', $dt)) {
        /* commentable_id beside commentable_type: the row says which table it points at */
        $stem = substr($n, 0, -3);
        foreach ([$stem . '_type', $stem] as $sib) if ($sib !== $n && in_array($sib, $GLOBALS['colNames'][$table] ?? [], true) && $sib !== $n) {
            if ($sib === $stem && !preg_match('/^(source|model|owner|subject|target|linked|trackable|schedulable|commentable)$/', $stem)) continue;
            return ['role' => 'reference', 'to' => null, 'polymorphic' => $sib];
        }
        $to = target($table, $n, $fkMap, $names);
        /* how sure: the database declares it, or the name says it exactly (ticket_id → tickets); anything else is a guess */
        $base = substr($n, 0, -3);
        $how = isset($GLOBALS['modelMap']["$table.$n"]) ? 'model' : (isset($fkMap["$table.$n"]) ? 'declared' : ($to !== null && in_array($to, [$base . 's', $base . 'es', preg_replace('/y$/', 'ies', $base)], true) ? 'name' : 'guess'));
        return ['role' => 'reference', 'to' => $to, 'how' => $how];
    }
    if (in_array($dt, ['date', 'datetime', 'timestamp'], true)) return ['role' => 'date'];
    if ($dt === 'time') return ['role' => 'time'];
    if ($dt === 'tinyint' && str_contains($ct, '(1)') || preg_match('/^(is|has|can|should|allow)_/', $n)) return ['role' => 'flag'];
    if (in_array($dt, ['decimal', 'double', 'float'], true) || (preg_match('/int/', $dt) && preg_match(MONEY, $n) && !preg_match('/(count|qty|quantity|days|seats|_no$)/', $n))) {
        return ['role' => preg_match(MONEY, $n) ? 'money' : 'number'];
    }
    if (preg_match('/int/', $dt)) return ['role' => 'number'];
    if ($dt === 'json') return ['role' => 'json'];
    if ($dt === 'enum') return ['role' => 'status'];
    if (preg_match('/(passport|nid|national_id|birth_cert)/', $n)) return ['role' => 'identity'];
    if (preg_match('/(phone|mobile|whatsapp|email|address)/', $n)) return ['role' => 'contact'];
    if (preg_match(STATUSY, $n)) return ['role' => 'status'];
    if (preg_match('/^(name|title|label|subject|full_name|company_name|.*_name)$/', $n)) return ['role' => 'label'];
    if (preg_match('/(_no|_number|code|reference|invoice|pnr|ticket_no)$/', $n)) return ['role' => 'reference_no'];
    if (in_array($dt, ['text', 'mediumtext', 'longtext'], true) || preg_match('/(note|remark|description|comment|message|reason|details)/', $n)) return ['role' => 'text'];
    if (preg_match('/(attachment|file|path|image|photo|logo|avatar|document|url)/', $n)) return ['role' => 'file'];
    return ['role' => 'value'];
}

$schema = ['generated' => date('c'), 'database_tables' => count($names), 'tables' => []];
foreach ($tables as $t => $list) {
    if (preg_match($skip, $t)) continue;
    $rows = (int) Db::one($pdo, "SELECT COUNT(*) n FROM `$t`")['n'];
    $entry = ['rows' => $rows, 'columns' => []];
    $hasDeleted = false;
    foreach ($list as $c) if ($c['c'] === 'deleted_at') $hasDeleted = true;
    if ($hasDeleted) $entry['live_rows'] = (int) Db::one($pdo, "SELECT COUNT(*) n FROM `$t` WHERE deleted_at IS NULL")['n'];
    $businessDate = null;
    foreach ($list as $c) {
        $r = role($c, $t, $fkMap, $names);
        $col = ['type' => $c['ct']] + $r;
        if ($c['cm'] !== '') $col['comment'] = $c['cm'];
        $n = $c['c'];
        if ($rows > 0 && $rows <= 200000 && $r['role'] !== 'secret' && $r['role'] !== 'key') {
            $filled = (int) Db::one($pdo, "SELECT COUNT(*) n FROM `$t` WHERE `$n` IS NOT NULL" . (in_array(strtolower($c['dt']), ['varchar', 'char', 'text'], true) ? " AND `$n` <> ''" : ''))['n'];
            $col['filled'] = round($filled / $rows, 3);
            if ($r['role'] === 'status') {
                $vals = Db::rows($pdo, "SELECT `$n` v, COUNT(*) n FROM `$t` WHERE `$n` IS NOT NULL AND `$n` <> '' GROUP BY `$n` ORDER BY n DESC LIMIT 31");
                if (count($vals) <= 30) $col['values'] = array_map(fn($v) => [(string) $v['v'], (int) $v['n']], $vals);
                else $col['role'] = 'value';   // thirty-plus distinct values is not a vocabulary
            }
            if ($r['role'] === 'date') {
                $mm = Db::one($pdo, "SELECT MIN(`$n`) a, MAX(`$n`) b FROM `$t` WHERE `$n` IS NOT NULL AND `$n` > '1971-01-01'");
                if ($mm && $mm['a']) $col['range'] = [substr((string) $mm['a'], 0, 10), substr((string) $mm['b'], 0, 10)];
                if (!$businessDate && !in_array($n, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at'], true)) $businessDate = $n;
            }
            /* No money totals: this file is committed to a public repository, and a
               column's total (salaries, journal debits) is the business's own figure.
               The figure answerers read amounts live from the ERP, under a session. */
        }
        $entry['columns'][$n] = $col;
    }
    if ($businessDate) $entry['date_column'] = $businessDate;
    elseif (isset($entry['columns']['created_at'])) $entry['date_column'] = 'created_at';
    if (isset($entry['date_column']['range'])) {}
    $dc = $entry['date_column'] ?? null;
    if ($dc && isset($entry['columns'][$dc]['range'])) $entry['range'] = $entry['columns'][$dc]['range'];
    $schema['tables'][$t] = $entry;
}

/* who points at whom — the reverse of every reference */
foreach ($schema['tables'] as $t => $e) foreach ($e['columns'] as $c => $col) {
    if (($col['role'] ?? '') === 'reference' && !empty($col['to']) && isset($schema['tables'][$col['to']])) $schema['tables'][$col['to']]['referenced_by'][] = "$t.$c";
}

/* ---------- what is wrong in the data ----------
   Counts and record ids only — never amounts, never names. Each finding is
   something the accountant can open and fix in the ERP. */
$health = [];
// a link that points at a row which is no longer there
foreach ($schema['tables'] as $t => $e) {
    if (($e['rows'] ?? 0) === 0) continue;
    foreach ($e['columns'] as $c => $col) {
        $to = $col['to'] ?? null;
        if (($col['role'] ?? '') !== 'reference' || !$to || !isset($schema['tables'][$to]) || $to === $t || ($col['how'] ?? 'guess') === 'guess') continue;
        if (!isset($schema['tables'][$to]['columns']['id'])) continue;
        $live = isset($e['columns']['deleted_at']) ? ' AND a.deleted_at IS NULL' : '';
        try {
            $r = Db::one($pdo, "SELECT COUNT(*) n, GROUP_CONCAT(a.id ORDER BY a.id SEPARATOR ',') ids FROM `$t` a LEFT JOIN `$to` b ON b.id = a.`$c` WHERE a.`$c` IS NOT NULL AND a.`$c` <> 0 AND b.id IS NULL$live");
        } catch (Throwable $x) { continue; }
        if ((int) $r['n'] > 0) $health[] = ['kind' => 'orphan', 'table' => $t, 'column' => $c, 'to' => $to, 'how' => $col['how'], 'rows' => (int) $r['n'], 'ids' => array_slice(array_map('intval', explode(',', (string) $r['ids'])), 0, 10)];
    }
}
// a journal entry whose debits and credits do not agree — the trial balance cannot balance with one
if (isset($schema['tables']['journal_items'], $schema['tables']['journal_entries'])) {
    $live = isset($schema['tables']['journal_entries']['columns']['deleted_at']) ? 'WHERE e.deleted_at IS NULL' : '';
    $rows = Db::rows($pdo, "SELECT e.id, e.reference FROM journal_entries e JOIN journal_items i ON i.journal_entry_id = e.id $live GROUP BY e.id, e.reference HAVING ROUND(SUM(i.debit) - SUM(i.credit), 2) <> 0 ORDER BY e.id LIMIT 50");
    if ($rows) $health[] = ['kind' => 'unbalanced_journal', 'table' => 'journal_entries', 'rows' => count($rows), 'ids' => array_map(fn($r) => (int) $r['id'], $rows), 'refs' => array_values(array_filter(array_map(fn($r) => (string) $r['reference'], $rows)))];
}
usort($health, fn($a, $b) => ($a['kind'] === 'unbalanced_journal' ? -1 : 0) - ($b['kind'] === 'unbalanced_journal' ? -1 : 0) ?: $b['rows'] <=> $a['rows']);
$schema['health'] = $health;

@mkdir(dirname($out), 0775, true);
file_put_contents($out, json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$roles = [];
foreach ($schema['tables'] as $e) foreach ($e['columns'] as $col) $roles[$col['role']] = ($roles[$col['role']] ?? 0) + 1;
arsort($roles);
printf("tables %d (of %d)  columns %d  file %s KB\nroles %s\n", count($schema['tables']), count($names), array_sum($roles), round(filesize($out) / 1024), json_encode($roles));
