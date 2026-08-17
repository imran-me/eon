<?php
/* Hostinger cron (hPanel → Cron Jobs), e.g.
     0 8 * * *   php /home/USER/domains/erp.epal.com.bd/public_html/eon/server/cron/morning-brief.php
   Computes the brief from the live dataset, stores it, and e-mails / webhooks it to the boss.
   Optional argument: company id. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$company = isset($argv[1]) ? (int) $argv[1] : null;
$D = Dataset::current($company, true);
$A = new Analytics($D, $company);
$b = $A->brief();
Memory::logDecisions($b['decisions'], $company);
Memory::setSetting('last_brief', ['date' => $b['date'], 'speak' => $b['speak'], 'at' => date('c')]);

$p = fn(string $s) => '<p style="font-family:system-ui,sans-serif;font-size:15px;line-height:1.5">' . htmlspecialchars($s) . '</p>';
$html = '<h2 style="font-family:system-ui,sans-serif">EON — morning brief, ' . date('l j F Y') . '</h2>' . implode('', array_map($p, $b['lines']));
$html .= '<h3 style="font-family:system-ui,sans-serif">Decisions</h3><ol style="font-family:system-ui,sans-serif">'
    . implode('', array_map(fn($d) => '<li><b>[' . htmlspecialchars($d['severity_label'] . ' · ' . $d['layer']) . ']</b> ' . htmlspecialchars($d['title']) . '<br><small>' . htmlspecialchars($d['recommend']) . '</small></li>', array_slice($b['decisions'], 0, 10)))
    . '</ol>';
$r = Notify::send('EON morning brief — ' . date('j M'), $b['speak'], $html);
echo $b['speak'], "\n", json_encode($r), "\n";
