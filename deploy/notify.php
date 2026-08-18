<?php
/* Called by deploy.sh when a deploy fails:  php deploy/notify.php "title" "text"
   Uses the same notify config as the cron jobs (server/config.local.php);
   silent when nothing is configured. Never reachable from the web. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("cli only\n"); }

$bootstrap = dirname(__DIR__) . '/server/bootstrap.php';
if (!is_file($bootstrap)) exit("server/bootstrap.php missing\n");
require_once $bootstrap;

$title = $argv[1] ?? 'EON deploy';
$text  = ($argv[2] ?? '') . "\n\nHost: " . gethostname() . "\nLog: deploy/deploy.log";
echo json_encode(Notify::send($title, $text)), "\n";
