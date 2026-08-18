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

// ---- 1b. the ERP: install it once from its own source, then leave it alone ----
// The ERP is the front door (see .htaccess). Its source is versioned with EON so a push
// deploys it, but its packages, key and .env belong to the host. Everything here is
// idempotent: after the first deploy it all short-circuits.
$erp = $root . '/erp';
if (is_file($erp . '/artisan') && is_file($erp . '/composer.json')) {
    require_once $server . '/bootstrap.php';   // Config, for the database EON already reads
    $bin = PHP_BINARY ?: 'php';

    // (a) .env — built from EON's own configuration, so the ERP and EON read one database
    if (!is_file($erp . '/.env')) {
        $db = (array) (Config::get('db') ?: []);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            foreach ((array) Config::get('origins', []) as $o) { if (is_string($o) && preg_match('~^https?://([^/]+)~', $o, $m)) { $host = $m[1]; break; } }
        }
        if ($host === '') $host = 'eon.gulfrabit.com';
        $env = is_file($erp . '/.env.example') ? (string) file_get_contents($erp . '/.env.example') : "APP_NAME=ERP\nAPP_KEY=\n";
        $set = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://' . $host,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) ($db['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($db['port'] ?? 3306),
            'DB_DATABASE' => (string) ($db['name'] ?? ''),
            'DB_USERNAME' => (string) ($db['user'] ?? ''),
            'DB_PASSWORD' => (string) ($db['pass'] ?? ''),
            // keep sessions, cache and queues off the database: EON's user may be read-only
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ];
        foreach ($set as $k => $v) {
            $kv = $k . '=' . (preg_match('/\s|#/', $v) ? '"' . $v . '"' : $v);
            $env = preg_match('/^' . preg_quote($k, '/') . '=.*$/m', $env)
                ? preg_replace('/^' . preg_quote($k, '/') . '=.*$/m', $kv, $env, 1)
                : rtrim($env) . "\n" . $kv . "\n";
        }
        if (@file_put_contents($erp . '/.env', $env) !== false) {
            @chmod($erp . '/.env', 0600);
            $line_ok = $set['DB_DATABASE'] !== '' ? 'database ' . $set['DB_DATABASE'] : 'NO DATABASE in EON config — edit erp/.env';
            $line('ERP .env written (' . $line_ok . ', sessions/cache on files)');
        } else {
            $line('! cannot write erp/.env');
            $ok = false;
        }
    }

    // (b) packages — Laravel cannot run without vendor/
    if (!is_file($erp . '/vendor/autoload.php')) {
        $composer = null;
        foreach (['composer', '/usr/local/bin/composer', '/usr/bin/composer', '/opt/cpanel/composer/bin/composer'] as $c) {
            $probe = @shell_exec(escapeshellcmd($c) . ' --version 2>/dev/null');
            if ($probe && stripos($probe, 'composer') !== false) { $composer = $c; break; }
        }
        if ($composer === null && is_file($erp . '/composer.phar')) $composer = escapeshellarg($bin) . ' ' . escapeshellarg($erp . '/composer.phar');
        if ($composer !== null && function_exists('shell_exec')) {
            $line('installing the ERP packages (first deploy only, this takes a few minutes)…');
            @shell_exec('cd ' . escapeshellarg($erp) . ' && ' . $composer . ' install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1');
            $line(is_file($erp . '/vendor/autoload.php') ? 'ERP packages ok' : '! composer install did not finish — run it over SSH in erp/');
        } else {
            $line('! composer not found on this host — run "composer install --no-dev -o" in erp/ over SSH');
        }
    }

    // (c) the writable folders Laravel insists on
    foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/public', 'bootstrap/cache'] as $d) {
        $dir = $erp . '/' . $d;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @chmod($dir, 0775);
    }

    // (d) the application key, once
    if (is_file($erp . '/vendor/autoload.php') && function_exists('shell_exec')) {
        $envTxt = (string) @file_get_contents($erp . '/.env');
        if (!preg_match('/^APP_KEY=base64:.+$/m', $envTxt)) {
            @shell_exec('cd ' . escapeshellarg($erp) . ' && ' . escapeshellarg($bin) . ' artisan key:generate --force 2>&1');
            $line(preg_match('/^APP_KEY=base64:.+$/m', (string) @file_get_contents($erp . '/.env')) ? 'ERP app key generated' : '! ERP app key not generated — run "php artisan key:generate" in erp/');
        }
        if (!file_exists($erp . '/public/storage')) @shell_exec('cd ' . escapeshellarg($erp) . ' && ' . escapeshellarg($bin) . ' artisan storage:link 2>&1');
        // a new checkout must not serve the previous deploy's compiled config/routes/views
        @shell_exec('cd ' . escapeshellarg($erp) . ' && ' . escapeshellarg($bin) . ' artisan optimize:clear 2>&1');
        $line('ERP caches cleared');
    }
}

// ---- 1b. the ERP is the front door: make sure the companion is appended to its pages ----
// Only once the ERP is actually installed in erp/ — see docs/erp-host.md. We never edit the
// ERP itself; PHP appends one script tag to its HTML responses (embed/eon-inject.php).
if (is_file($root . '/erp/public/index.php')) {
    $ini = $root . '/.user.ini';
    $want = 'auto_append_file = "' . $root . '/embed/eon-inject.php"';
    $have = is_file($ini) ? (string) @file_get_contents($ini) : '';
    if (!str_contains($have, 'eon-inject.php')) {
        $next = trim(preg_replace('/^\s*auto_append_file\s*=.*$/mi', '', $have) ?? '');
        if (@file_put_contents($ini, ($next ? $next . "\n" : '') . $want . "\n") !== false) {
            $line('ERP detected → companion injection enabled (.user.ini, takes effect within 5 minutes)');
        } else {
            $line('! ERP detected but .user.ini is not writable — add by hand: ' . $want);
        }
    } else {
        $line('ERP detected · companion injection already on');
    }
}

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
