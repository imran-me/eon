<?php
/* EON · embed self-check — is the companion being added to PHP pages here?
   GET /embed/check.php → JSON. Reveals nothing sensitive: only whether PHP's
   auto_prepend/append_file points at eon-inject.php, and whether the buffer
   callback actually fired for this very response. */
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
$pre = (string) ini_get('auto_prepend_file');
$app = (string) ini_get('auto_append_file');
$mode = str_contains($pre, 'eon-inject.php') ? 'prepend' : (str_contains($app, 'eon-inject.php') ? 'append' : 'none');
echo json_encode([
    'ok' => true,
    'mode' => $mode,
    'placeholder_filled' => !str_contains($pre . $app, '__EON_ROOT__'),
    'injector_loaded' => defined('EON_INJECT_LOADED'),
    'buffer_open' => ob_get_level() > 0,
    'sapi' => PHP_SAPI,
], JSON_UNESCAPED_SLASHES), "\n";
