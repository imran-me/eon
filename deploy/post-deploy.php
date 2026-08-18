<?php
/* ============================================================
   EON — post-deploy. Runs after every checkout (deploy.sh, the
   hPanel Git hook, or by hand):

       php deploy/post-deploy.php

   It is idempotent and never destructive: it creates the runtime
   folders, installs composer packages when they are missing,
   applies EON's own schema when its database is reachable, clears
   the dataset cache and prints a one-screen health report.
   ============================================================ */
declare(strict_types=1);

$root = dirname(__DIR__);
$server = $root . '/server';
$ok = true;
$out = [];
$line = function (string $s) use (&$out) { $out[] = $s; echo $s, "\n"; };   // print as we go — a fatal further down must not swallow the earlier lines

// without this a fatal error ended the deploy silently: "post-deploy reported problems" and nothing else
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "! post-deploy stopped: {$e['message']} ({$e['file']}:{$e['line']})\n";
    }
});

// ---- 1. runtime folders (git keeps them empty; the app needs them writable) ----
foreach (['storage/cache', 'storage/logs', 'storage/data'] as $d) {
    $p = $server . '/' . $d;
    if (!is_dir($p) && !@mkdir($p, 0755, true)) { $line("! cannot create $d"); $ok = false; continue; }
    if (!is_writable($p)) { @chmod($p, 0755); if (!is_writable($p)) { $line("! not writable: $d"); $ok = false; } }
}
// belt and braces: these must never be served even if the .htaccess is lost
foreach (['storage', 'lib', 'install', 'cron', 'py'] as $d) {
    $h = $server . '/' . $d . '/.htaccess';
    if (is_dir($server . '/' . $d) && !is_file($h)) @file_put_contents($h, "Require all denied\n");
}
$line('folders ok');

// ---- 2. composer packages (the Anthropic SDK) — optional, EON runs without them ----
if (!is_file($server . '/vendor/autoload.php')) {
    $composer = null;
    foreach (['composer', '/usr/local/bin/composer', '/usr/bin/composer', 'composer.phar'] as $c) {
        $probe = @shell_exec(escapeshellcmd($c) . ' --version 2>/dev/null');
        if ($probe && stripos($probe, 'composer') !== false) { $composer = $c; break; }
    }
    if ($composer && function_exists('shell_exec')) {
        $line('installing composer packages…');
        @shell_exec('cd ' . escapeshellarg($server) . ' && ' . escapeshellcmd($composer) . ' install --no-dev --no-interaction --optimize-autoloader 2>&1');
        $line(is_file($server . '/vendor/autoload.php') ? 'composer ok' : '! composer install did not produce vendor/ — run it over SSH');
    } else {
        $line('- no composer on this host: EON will answer with the offline brain until vendor/ is uploaded');
    }
} else {
    $line('composer ok');
}

// ---- 3. load EON itself ----
if (!is_file($server . '/bootstrap.php')) { $line('! server/bootstrap.php missing'); exit(1); }
require_once $server . '/bootstrap.php';
ini_set('display_errors', '1');   // CLI: the deploy log should carry the reason, not hide it

if (!is_file($server . '/config.local.php')) {
    $line('- config.local.php missing → demo mode. Copy config.example.php and set the token, database and boss.');
}

// ---- 4. EON's own schema, when its database is configured ----
$eonDb = Config::get('eon_db') ?: (Config::dbEnabled() ? Config::get('db') : null);
if ($eonDb) {
    try {
        $pdo = Db::eon();
        $has = Db::tableExists($pdo, 'eon_settings');
        if (!$has) {
            $sql = (string) file_get_contents($server . '/install/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                $pdo->exec($stmt);
            }
            $line('schema applied (eon_* tables created)');
        } else {
            $line('schema ok');
        }
    } catch (Throwable $e) {
        $line('! EON database: ' . $e->getMessage());
        $line('  (EON falls back to JSON files in server/storage/data — it still works)');
    }
} else {
    $line('- no EON database configured: memory lives in server/storage/data/*.json');
}

// ---- 5. fresh data on the next request ----
try { Dataset::cacheClear(); $line('cache cleared'); } catch (Throwable $e) { $line('! cache: ' . $e->getMessage()); }

// ---- 6. health report ----
// the commit: from deploy.sh, else from the checkout (git, then .git/HEAD), else unknown
$sha = (string) (getenv('EON_DEPLOY_SHA') ?: '');
if ($sha === '' && function_exists('shell_exec')) {
    $sha = @trim((string) @shell_exec('cd ' . escapeshellarg($root) . ' && git rev-parse --short HEAD 2>/dev/null'));
}
if ($sha === '' && is_file($root . '/.git/HEAD')) {
    $head = trim((string) @file_get_contents($root . '/.git/HEAD'));
    $ref = str_starts_with($head, 'ref: ') ? trim((string) @file_get_contents($root . '/.git/' . substr($head, 5))) : $head;
    $sha = substr($ref, 0, 7);
}
$sha = $sha !== '' ? $sha : 'unknown';
$erpOk = false;
try { $erpOk = Config::dbEnabled() && Db::available(); } catch (Throwable $e) { $erpOk = false; }
$state = [
    'commit'     => $sha,
    'deployed_at'=> date('c'),
    'php'        => PHP_VERSION,
    'token'      => ((string) Config::get('token', '') !== ''),
    'erp_db'     => $erpOk,
    'eon_db'     => (function () { try { return Db::eonAvailable(); } catch (Throwable $e) { return false; } })(),
    'llm'        => Config::llmEnabled(),
    'source'     => Dataset::source(),
    'ok'         => $ok,
];
@file_put_contents(__DIR__ . '/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$line(sprintf(
    'EON %s · php %s · token %s · ERP db %s · EON db %s · model %s · data %s',
    $sha,
    PHP_VERSION,
    $state['token'] ? 'set' : 'MISSING',
    $state['erp_db'] ? 'ok' : 'off',
    $state['eon_db'] ? 'ok' : 'files',
    $state['llm'] ? 'on' : 'offline brain',
    $state['source']
));
if (!$state['token'] && ($state['erp_db'] || Config::llmKeyPresent())) {
    $line('! set "token" in server/config.local.php — the API refuses to serve real data without it');
    $ok = false;
}

exit($ok ? 0 : 1);
