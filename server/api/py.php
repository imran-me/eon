<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET ?cmd=forecast|anomalies|evaluate|report|health &company= &months= &kind= &employee=  → Python analytics over the current dataset */
Http::run(function () {
    Http::auth();
    $cmd = (string) Http::q('cmd', 'forecast'); $company = Http::intq('company');
    if (!in_array($cmd, ['forecast', 'anomalies', 'evaluate', 'report', 'health'], true)) Http::fail(400, 'cmd must be forecast|anomalies|evaluate|report|health');
    if ($cmd === 'health') Http::json(['ok' => true, 'python' => Py::available(), 'bin' => Py::bin(), 'detail' => Py::run('health', null)]);
    $D = Dataset::current($company);
    $args = ['--company' => $company];
    if ($cmd === 'forecast') $args['--months'] = (int) (Http::q('months') ?: 3);
    if ($cmd === 'evaluate') { if (($e = Http::intq('employee')) !== null) $args['--employee'] = $e; else $args['--all'] = true; }
    if ($cmd === 'report') { $kind = (string) Http::q('kind', 'receivables'); $args['--kind'] = $kind; $args['--out'] = EON_ROOT . '/storage/data/report-' . preg_replace('/[^a-z]/', '', $kind) . '-' . date('Ymd-His') . '.xlsx'; }
    $r = Py::run($cmd, $D, $args);
    if ($cmd === 'report' && ($r['ok'] ?? false) && !empty($r['file'])) $r['download'] = 'file.php?name=' . rawurlencode(basename($r['file']));
    Http::json(['ok' => (bool) ($r['ok'] ?? false), 'cmd' => $cmd, 'company' => $company, 'source' => Dataset::source(), 'data' => $r]);
});
