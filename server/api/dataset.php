<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET ?company=ID&fresh=1 → the full EON dataset (live ERP when configured, else demo). */
Http::run(function () {
    Http::auth();
    $company = Http::intq('company');
    $D = Dataset::current($company, (bool) Http::q('fresh', false));
    Http::json($D);
});
