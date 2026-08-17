<?php
/* Hostinger cron:  0 * * * *   php .../server/cron/watch.php
   Hourly watcher: any NEW critical/high decision since the last run → notify the boss. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$company = isset($argv[1]) ? (int) $argv[1] : null;
$A = new Analytics(Dataset::current($company, true), $company);
$dec = array_filter($A->decisions(), fn($d) => $d['severity'] >= 4);
$seen = Memory::setting('watch_seen', []) ?: [];
$new = []; $now = [];
foreach ($dec as $d) { $key = md5($d['layer'] . '|' . $d['title']); $now[$key] = date('c'); if (!isset($seen[$key])) $new[] = $d; }
Memory::setSetting('watch_seen', $now);
if ($new) {
    $text = implode("\n", array_map(fn($d) => "• [{$d['severity_label']}] {$d['title']}\n  → {$d['recommend']}", $new));
    Notify::send('EON alert — ' . count($new) . ' new critical item' . (count($new) > 1 ? 's' : ''), $text);
}
echo count($new), " new critical item(s)\n";
