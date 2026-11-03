<?php

namespace Modules\EonAi\Support;

use Illuminate\Support\Facades\Http;

/**
 * The signed handshake with the EON server — the ONLY outbound call this
 * module makes today. It sends no ERP data: just who is calling, when, and a
 * signature proving the ERP holds the shared key. EON must answer with a
 * proof of its own, so both sides show they hold the same key.
 *
 * Contract (EON implements it on its side; see the connection page):
 *
 *   GET  {server_url}/erp-bridge/handshake
 *   X-Erp-Id:        epal-erp
 *   X-Erp-Timestamp: <unix seconds>
 *   X-Erp-Signature: hex HMAC-SHA256(key, "<timestamp>\nGET\n/erp-bridge/handshake\n" . sha256(""))
 *
 *   200 {"ok": true, "app": "EON", "version": "…",
 *        "proof": hex HMAC-SHA256(key, "<the same timestamp>\nEON-OK")}
 */
class Handshake
{
    public const PATH = '/erp-bridge/handshake';
    public const TIMEOUT = 6;          // seconds; the ERP never waits longer

    public static function sign(string $key, string $timestamp, string $method, string $path, string $body = ''): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $body), $key);
    }

    /**
     * Refuse addresses that are not public https hosts — the ERP server must
     * not be pointed at itself or at the hosting network. Plain http and
     * private addresses are allowed only when APP_ENV is local or testing.
     * Returns null when fine, or the reason it is refused.
     */
    public static function refuse(?string $url): ?string
    {
        if (! $url) {
            return 'Enter EON\'s server address first.';
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        $local = app()->environment('local', 'testing');   // never true in production

        if (! $host || ! in_array($scheme, $local ? ['https', 'http'] : ['https'], true)) {
            return 'The address must start with https:// and name a host.';
        }

        if ($local) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        if (! $ips) {
            return 'That host name does not resolve.';
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'That address points inside a private network, which is not allowed.';
            }
        }

        return null;
    }

    /**
     * Run the handshake. Always returns a result array; never throws.
     * ['ok' => bool, 'message' => string, 'ms' => int|null, 'version' => ?string]
     */
    public static function run(?string $serverUrl, ?string $key): array
    {
        if ($reason = self::refuse($serverUrl)) {
            return ['ok' => false, 'message' => $reason, 'ms' => null, 'version' => null];
        }
        if (! $key) {
            return ['ok' => false, 'message' => 'Generate or enter the shared key first.', 'ms' => null, 'version' => null];
        }

        $timestamp = (string) time();
        $url = rtrim($serverUrl, '/') . self::PATH;
        $started = microtime(true);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(4)
                ->withOptions(['allow_redirects' => false])
                ->acceptJson()
                ->withHeaders([
                    'X-Erp-Id'        => 'epal-erp',
                    'X-Erp-Timestamp' => $timestamp,
                    'X-Erp-Signature' => self::sign($key, $timestamp, 'GET', self::PATH),
                ])
                ->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Cannot reach the EON server (' . class_basename($e) . '). Check the address, and that the server is up.', 'ms' => null, 'version' => null];
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $status = $response->status();

        if ($status === 404) {
            return ['ok' => false, 'message' => "The EON server answered ({$ms} ms) but has no ERP handshake yet — add " . self::PATH . ' on the EON side.', 'ms' => $ms, 'version' => null];
        }
        if (in_array($status, [401, 403], true)) {
            return ['ok' => false, 'message' => 'The EON server refused the key. The same key must be set on both sides.', 'ms' => $ms, 'version' => null];
        }
        if ($status >= 300 && $status < 400) {
            return ['ok' => false, 'message' => "The EON server redirected (HTTP {$status}). Enter the final address — redirects are not followed.", 'ms' => $ms, 'version' => null];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'message' => "The EON server returned HTTP {$status}.", 'ms' => $ms, 'version' => null];
        }

        $json = $response->json();
        $expected = hash_hmac('sha256', $timestamp . "\nEON-OK", $key);

        if (! is_array($json) || empty($json['ok']) || ! is_string($json['proof'] ?? null) || ! hash_equals($expected, $json['proof'])) {
            return ['ok' => false, 'message' => 'Something answered, but it could not prove it holds the key — it is not a correctly configured EON.', 'ms' => $ms, 'version' => null];
        }

        $version = is_string($json['version'] ?? null) ? mb_substr($json['version'], 0, 60) : null;

        return ['ok' => true, 'message' => 'Connected. Both sides hold the same key' . ($version ? " — EON {$version}" : '') . " ({$ms} ms).", 'ms' => $ms, 'version' => $version];
    }
}
