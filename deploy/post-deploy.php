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
// shared hosts (this one included) disable exec/shell_exec for the web user — never call
// them directly; everything that can be done in-process is, and the rest is reported.
$sh = function (string $cmd): ?string {
    if (function_exists('shell_exec')) { try { return (string) @shell_exec($cmd); } catch (Throwable $e) { return null; } }
    if (function_exists('exec')) { $o = []; @exec($cmd, $o); return implode("\n", $o); }
    if (function_exists('proc_open')) {
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) { $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); return $out; }
    }
    return null;
};
$canShell = function_exists('shell_exec') || function_exists('exec') || function_exists('proc_open');
if (is_file($erp . '/artisan') && is_file($erp . '/composer.json')) {
    require_once $server . '/bootstrap.php';   // Config, for the database EON already reads
    $bin = PHP_BINARY ?: 'php';

    // (0) the folders Laravel insists on — FIRST, and in-process, so nothing else can prevent them
    foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/testing', 'storage/logs', 'storage/app/public', 'storage/app/private', 'bootstrap/cache'] as $d) {
        $dir = $erp . '/' . $d;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @chmod($dir, 0775);
    }
    // the public/storage link, without artisan (it needs exec on some builds)
    if (!file_exists($erp . '/public/storage') && function_exists('symlink')) @symlink($erp . '/storage/app/public', $erp . '/public/storage');

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
        foreach ($canShell ? ['composer', '/usr/local/bin/composer', '/usr/bin/composer', '/opt/cpanel/composer/bin/composer'] : [] as $c) {
            $probe = $sh(escapeshellcmd($c) . ' --version 2>/dev/null');
            if ($probe && stripos($probe, 'composer') !== false) { $composer = $c; break; }
        }
        if ($composer === null && is_file($erp . '/composer.phar')) $composer = escapeshellarg($bin) . ' ' . escapeshellarg($erp . '/composer.phar');
        // shared hosts often ship composer only as a phar somewhere on disk, or the cron PATH lacks it
        if ($composer === null) {
            foreach (['/usr/local/bin/composer.phar', '/usr/bin/composer.phar', '/opt/composer/composer.phar', getenv('HOME') . '/composer.phar', getenv('HOME') . '/bin/composer'] as $phar) {
                if ($phar && is_file($phar)) { $composer = escapeshellarg($bin) . ' ' . escapeshellarg($phar); break; }
            }
        }
        // last resort: fetch composer itself (official installer, verified by its own checksum) into erp/
        if ($composer === null && $canShell && ini_get('allow_url_fopen')) {
            $line('composer not on this host — downloading composer.phar into erp/ (once)…');
            $setup = @file_get_contents('https://getcomposer.org/installer');
            $sig = trim((string) @file_get_contents('https://composer.github.io/installer.sig'));
            if ($setup !== false && $sig !== '' && hash('sha384', $setup) === $sig) {
                @file_put_contents($erp . '/composer-setup.php', $setup);
                $sh('cd ' . escapeshellarg($erp) . ' && ' . escapeshellarg($bin) . ' composer-setup.php --quiet 2>&1');
                @unlink($erp . '/composer-setup.php');
                if (is_file($erp . '/composer.phar')) $composer = escapeshellarg($bin) . ' ' . escapeshellarg($erp . '/composer.phar');
                else $line('! composer download did not produce composer.phar');
            } else {
                $line('! composer installer could not be fetched or failed its checksum');
            }
        }
        if ($composer !== null && $canShell) {
            $line('installing the ERP packages (first deploy only, this takes a few minutes)…');
            $cout = (string) $sh('cd ' . escapeshellarg($erp) . ' && COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1 ' . $composer . ' install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1');
            if (is_file($erp . '/vendor/autoload.php')) $line('ERP packages ok');
            else { $line('! composer install did not finish — run it over SSH in erp/'); foreach (array_slice(array_filter(explode(PHP_EOL, $cout)), -6) as $cl) $line('    ' . $cl); }
        } else {
            $line($canShell ? '! composer not found on this host — run "composer install --no-dev -o" in erp/ over SSH'
                            : '- exec is disabled for the web user: install the ERP packages once over SSH → bash deploy/erp-install.sh');
        }
    }

    // (c) the application key — written in-process (artisan key:generate needs nothing we lack, but exec may be off)
    $envTxt = is_file($erp . '/.env') ? (string) @file_get_contents($erp . '/.env') : '';
    if ($envTxt !== '' && !preg_match('/^APP_KEY=base64:.+$/m', $envTxt)) {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envTxt = preg_match('/^APP_KEY=.*$/m', $envTxt) ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $envTxt, 1) : rtrim($envTxt) . "\nAPP_KEY=" . $key . "\n";
        @file_put_contents($erp . '/.env', $envTxt);
        $line('ERP app key generated');
    }

    // (d) a new checkout must not serve the previous deploy's compiled config/routes/views — delete them in-process
    foreach (['bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php', 'bootstrap/cache/services.php', 'bootstrap/cache/packages.php', 'bootstrap/cache/events.php'] as $f) {
        if (is_file($erp . '/' . $f) && !in_array(basename($f), ['services.php', 'packages.php'], true)) @unlink($erp . '/' . $f);
    }
    foreach (glob($erp . '/storage/framework/views/*.php') ?: [] as $f) @unlink($f);
    $line(is_file($erp . '/vendor/autoload.php') ? 'ERP ready (packages present, caches cleared)' : 'ERP source present, packages missing');
}

// ---- 1a. the site .htaccess carries this host's absolute path for auto_append_file ----
// (a placeholder in git, the real path on the host — the same file works on any account)
$ht = $root . '/.htaccess';
if (is_file($ht)) {
    $txt = (string) @file_get_contents($ht);
    $real = str_replace(DIRECTORY_SEPARATOR, '/', $root);
    if (str_contains($txt, '__EON_ROOT__')) {
        if (@file_put_contents($ht, str_replace('__EON_ROOT__', $real, $txt)) !== false) $line('site .htaccess: append path set to ' . $real);
        else $line('! cannot write the site .htaccess');
    } elseif (preg_match('~auto_append_file "([^"]+)/embed/eon-inject\.php"~', $txt, $m) && $m[1] !== $real) {
        // moved to another account/path: point it at the new one
        if (@file_put_contents($ht, str_replace($m[1] . '/embed/eon-inject.php', $real . '/embed/eon-inject.php', $txt)) !== false) $line('site .htaccess: append path updated to ' . $real);
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
        $probe = $canShell ? $sh(escapeshellcmd($c) . ' --version 2>/dev/null') : null;
        if ($probe && stripos($probe, 'composer') !== false) { $composer = $c; break; }
    }
    if ($composer && $canShell) {
        $line('installing composer packages…');
        $sh('cd ' . escapeshellarg($server) . ' && ' . escapeshellcmd($composer) . ' install --no-dev --no-interaction --optimize-autoloader 2>&1');
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
    $sha = $canShell ? trim((string) $sh('cd ' . escapeshellarg($root) . ' && git rev-parse --short HEAD 2>/dev/null')) : '';
    if ($sha === '') { $head = trim((string) @file_get_contents($root . '/.git/HEAD')); if (str_starts_with($head, 'ref:')) $sha = substr(trim((string) @file_get_contents($root . '/.git/' . trim(substr($head, 4)))), 0, 7); elseif ($head !== '') $sha = substr($head, 0, 7); }
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
