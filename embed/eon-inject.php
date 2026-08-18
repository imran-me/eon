<?php
/* ============================================================
   EON · inject — put EON on the ERP without touching one line of it.

   Loaded by PHP BEFORE the ERP runs (auto_prepend_file, set in the
   site .htaccess by deploy/post-deploy.php). It opens an output
   buffer whose callback sees the finished page on its way out and,
   only when that page is a real HTML document, appends the one
   script tag that loads EON. It never edits, buffers away or delays
   anything else: JSON, downloads, redirects, AJAX fragments and
   non-200 responses pass through byte for byte.

   Why prepend + buffer, not auto_append_file: Laravel sends the
   response and terminates the request itself; on LiteSpeed anything
   appended after that never reaches the client. A buffer callback
   runs inside the response, so it always does.

   Also fine as auto_append_file on hosts where that works — the
   guard at the bottom handles both modes.
   ============================================================ */

if (PHP_SAPI === 'cli') return;
if (defined('EON_INJECT_LOADED')) return;
define('EON_INJECT_LOADED', true);

/* ---------- where EON lives ---------- */
function eon_inject_src(): string
{
    $base = getenv('EON_BASE') ?: '';
    if ($base === '') {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = $_SERVER['HTTP_HOST'] ?? 'eon.gulfrabit.com';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    return rtrim($base, '/') . '/embed/eon-embed.js';
}

/* ---------- is this response a page EON belongs on? ---------- */
function eon_inject_wanted(string $html): bool
{
    // only whole HTML documents
    if (stripos($html, '</body>') === false && stripos($html, '</html>') === false) return false;
    if (stripos($html, 'eon-embed.js') !== false) return false;                     // already there
    // never a fragment or an API answer
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') return false;
    if (isset($_SERVER['HTTP_HX_REQUEST']) || isset($_SERVER['HTTP_TURBO_FRAME']) || isset($_SERVER['HTTP_X_INERTIA'])) return false;
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;             // only page views
    // EON's own pages and API don't need themselves appended
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('~^/(eon|embed|server|app|ai-companion|deploy)(/|$)~', $uri)) return false;
    // the headers the app has set by now — must be an HTML page, not a download
    if (function_exists('headers_list')) {
        foreach (headers_list() as $h) {
            $lh = strtolower($h);
            if (str_starts_with($lh, 'content-type:') && !str_contains($lh, 'text/html')) return false;
            if (str_starts_with($lh, 'content-disposition:') && str_contains($lh, 'attachment')) return false;
            if (str_starts_with($lh, 'location:')) return false;                     // a redirect
        }
    }
    if (function_exists('http_response_code')) { $c = http_response_code(); if ($c && $c !== 200) return false; }
    return true;
}

function eon_inject_tag(): string
{
    return "\n<!-- EON companion (appended by embed/eon-inject.php; the ERP source is untouched) -->\n"
         . '<script src="' . htmlspecialchars(eon_inject_src(), ENT_QUOTES) . '" defer></script>' . "\n";
}

/* ---------- prepend mode: buffer the whole response, append on the way out ---------- */
// (as auto_prepend_file we run before any output: nothing has been sent, so buffer)
if (!headers_sent()) {
    ob_start(function (string $buf, int $phase): string {
        static $done = false;
        if ($done) return $buf;
        // act once, when the final chunk goes out (or on a single-shot flush)
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL) && !($phase & PHP_OUTPUT_HANDLER_END)) return $buf;
        $done = true;
        if (!eon_inject_wanted($buf)) return $buf;
        $tag = eon_inject_tag();
        // before </body> when it is there, else at the very end
        $pos = strripos($buf, '</body>');
        return $pos === false ? $buf . $tag : substr($buf, 0, $pos) . $tag . substr($buf, $pos);
    });
    return;
}

/* ---------- append mode (auto_append_file on hosts where that still reaches the client) ---------- */
// headers already sent → the page went out without us; add the tag after it
if (eon_inject_wanted('</body>')) echo eon_inject_tag();
