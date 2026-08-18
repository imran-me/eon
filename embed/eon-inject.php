<?php
/* ============================================================
   EON · inject — put EON on the ERP without touching one line of it.

   The ERP is not ours to edit. PHP can append to every response it
   produces instead, so EON rides along and the ERP source stays
   exactly as it is (and keeps updating from its own repository).

   Enable it for the ERP only — in the ERP's own .htaccess or in the
   subdomain's, next to where the ERP runs:

       php_value auto_append_file "/home/uXXXXXXX/domains/gulfrabit.com/public_html/eon/embed/eon-inject.php"

   or, on hosts where .htaccess cannot set php values, in .user.ini:

       auto_append_file = "/home/…/eon/embed/eon-inject.php"

   It appends the one script tag, and ONLY to real HTML pages:
   JSON, downloads, redirects, AJAX fragments and non-200 responses
   are left untouched, so nothing the ERP returns is ever corrupted.
   ============================================================ */

(function () {
    // --- only complete HTML documents ---
    if (PHP_SAPI === 'cli') return;
    if (function_exists('http_response_code') && (int) http_response_code() !== 200) return;

    $type = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type:') === 0) $type = strtolower(trim(substr($h, 13)));
        if (stripos($h, 'content-disposition:') === 0 && stripos($h, 'attachment') !== false) return;   // a download
    }
    if ($type !== '' && strpos($type, 'text/html') === false) return;          // json, csv, pdf, xml…
    if ($type === '' && !headers_sent()) { /* no type set yet: PHP defaults to text/html */ }

    // an XHR/fetch fragment is not a page — never inject into one
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false && stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') === false;
    if ($xhr || $wantsJson) return;
    if (stripos($_SERVER['HTTP_TURBO_FRAME'] ?? '', '') === 0 && isset($_SERVER['HTTP_HX_REQUEST'])) return;   // htmx partial

    // --- where EON lives (edit if the panel moves) ---
    $base = getenv('EON_BASE') ?: '';
    if ($base === '') {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'eon.gulfrabit.com';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    $src = rtrim($base, '/') . '/embed/eon-embed.js';

    echo "\n<!-- EON companion (appended by embed/eon-inject.php; the ERP source is untouched) -->\n"
       . '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>' . "\n";
})();
