<?php
/* ============================================================
   EON — GitHub push webhook (instant deploy).

   Only needed when this site has no hPanel GIT page of its own
   (layout B: the folder lives inside another website's public_html).
   With hPanel Git connected, use Hostinger's own auto-deployment
   URL instead and leave this file unconfigured — it stays inert.

   Setup
     1. cp deploy/deploy.env.example deploy/deploy.env   (git-ignored)
        secret=<a long random string>
        src=/home/uXXXXXXX/eon-src                 # the git checkout
        publish=/home/uXXXXXXX/domains/gulfrabit.com/public_html/eon
        branch=main
     2. GitHub → repo → Settings → Webhooks → Add webhook
        Payload URL:  https://eon.gulfrabit.com/deploy/webhook.php
        Content type: application/json
        Secret:       the same secret
        Events:       just the push event

   Every request is verified with HMAC-SHA256 (X-Hub-Signature-256),
   so only GitHub can trigger a deploy.
   ============================================================ */
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function out(int $code, array $body): never { http_response_code($code); echo json_encode($body); exit; }

// ---- settings ----
$cfg = [];
$envFile = __DIR__ . '/deploy.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
        [$k, $v] = explode('=', $l, 2);
        $cfg[strtolower(trim($k))] = trim($v, " \t\"'");
    }
}
$secret  = $cfg['secret']  ?? (getenv('EON_DEPLOY_SECRET') ?: '');
$src     = $cfg['src']     ?? dirname(__DIR__);
$publish = $cfg['publish'] ?? '';
$branch  = $cfg['branch']  ?? 'main';

if ($secret === '') out(503, ['ok' => false, 'error' => 'webhook not configured (deploy/deploy.env)']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') out(405, ['ok' => false, 'error' => 'POST only']);

// ---- verify it really is GitHub ----
$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 5_000_000) out(413, ['ok' => false, 'error' => 'payload too large']);
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$mine = 'sha256=' . hash_hmac('sha256', $raw, $secret);
if (!$sig || !hash_equals($mine, $sig)) out(401, ['ok' => false, 'error' => 'bad signature']);

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event === 'ping') out(200, ['ok' => true, 'pong' => true]);
if ($event !== 'push') out(200, ['ok' => true, 'ignored' => $event]);

$payload = json_decode($raw, true) ?: [];
$ref = (string) ($payload['ref'] ?? '');
if ($ref !== '' && $ref !== 'refs/heads/' . $branch) out(200, ['ok' => true, 'ignored' => $ref]);

// ---- don't stampede: at most one deploy every 10 seconds ----
$stamp = __DIR__ . '/.webhook.last';
if (is_file($stamp) && time() - (int) @filemtime($stamp) < 10) out(202, ['ok' => true, 'skipped' => 'a deploy just ran']);
@touch($stamp);

// ---- run the same script cron runs ----
$script = rtrim($src, '/') . '/deploy/deploy.sh';
if (!is_file($script)) out(500, ['ok' => false, 'error' => 'deploy.sh not found at ' . $script]);
if (!function_exists('shell_exec')) out(500, ['ok' => false, 'error' => 'shell_exec is disabled on this host — use the cron job instead']);

$cmd = ($publish !== '' ? 'EON_PUBLISH_DIR=' . escapeshellarg($publish) . ' ' : '')
     . 'EON_BRANCH=' . escapeshellarg($branch) . ' '
     . '/bin/bash ' . escapeshellarg($script) . ' --quiet 2>&1';
$output = (string) @shell_exec($cmd);

$sha = substr((string) ($payload['after'] ?? ''), 0, 7);
out(200, ['ok' => true, 'commit' => $sha, 'lines' => array_slice(array_filter(explode("\n", trim($output))), -6)]);
