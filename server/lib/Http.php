<?php
declare(strict_types=1);

/* JSON responses, CORS, auth, request parsing for the API endpoints. */
final class Http
{
    public static function cors(): void
    {
        $origins = Config::get('origins', ['*']);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array('*', $origins, true)) header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        elseif ($origin && in_array($origin, $origins, true)) header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-EON-Token');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
    }

    /** Bearer / header / query token. Returns true when no token is configured (open mode). */
    public static function auth(bool $required = true): bool
    {
        $token = (string) Config::get('token', '');
        if ($token === '') return true;
        $given = '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) $given = trim($m[1]);
        if (!$given) $given = $_SERVER['HTTP_X_EON_TOKEN'] ?? ($_GET['token'] ?? '');
        $ok = $given !== '' && hash_equals($token, (string) $given);
        if (!$ok && $required) self::fail(401, 'unauthorised — send Authorization: Bearer <EON token>');
        return $ok;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    public static function fail(int $status, string $message, array $extra = []): never
    {
        Log::warn("http $status: $message");
        self::json(['ok' => false, 'error' => $message] + $extra, $status);
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return $_POST ?: [];
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public static function q(string $k, mixed $d = null): mixed { return $_GET[$k] ?? $d; }
    public static function intq(string $k, ?int $d = null): ?int { $v = $_GET[$k] ?? null; return ($v === null || $v === '') ? $d : (int) $v; }

    /** run an endpoint with uniform error handling */
    public static function run(callable $fn): void
    {
        self::cors();
        try { $fn(); }
        catch (Throwable $e) { Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]); self::json(['ok' => false, 'error' => $e->getMessage()], 500); }
    }
}
