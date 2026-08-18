<?php
/* EON · embed self-check — is the companion being appended to PHP pages here?
   GET /embed/check.php → JSON. Reveals nothing sensitive: only whether PHP's
   auto_append_file points at eon-inject.php, and which mechanism set it. */
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
$aaf = (string) ini_get('auto_append_file');
echo json_encode([
    'ok' => true,
    'auto_append_file' => $aaf !== '' && str_contains($aaf, 'eon-inject.php'),
    'via' => $aaf === '' ? 'none' : (str_contains($aaf, '__EON_ROOT__') ? 'placeholder-not-filled' : 'set'),
    'sapi' => PHP_SAPI,
    'user_ini' => (string) ini_get('user_ini.filename'),
], JSON_UNESCAPED_SLASHES), "\n";
