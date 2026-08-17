<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET ?name=report-kind-YYYYMMDD-HHMMSS.xlsx → download a generated report from storage/data (report-* files only) */
Http::run(function () {
    Http::auth();
    $name = basename((string) Http::q('name', ''));
    if (!preg_match('/^report-[a-z]+-\d{8}-\d{6}\.(xlsx|csv)$/', $name)) Http::fail(400, 'invalid file name');
    $f = EON_ROOT . '/storage/data/' . $name;
    if (!is_file($f)) Http::fail(404, 'no such report');
    header('Content-Type: ' . (str_ends_with($name, '.xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=utf-8'));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: no-store');
    readfile($f);
    exit;
});
