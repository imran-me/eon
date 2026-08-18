<?php
declare(strict_types=1);

/* ============================================================
   EON · ErpSession — "if you are logged into the ERP, you are you."

   EON sits on the same host and the same origin as the ERP, so the
   boss should never be asked for a second credential. This reads the
   ERP's own Laravel session — the cookie the ERP itself set — and
   reports which user it belongs to.

   How the trust works (all of it verified, nothing assumed):

     1. the session cookie is decrypted with the ERP's APP_KEY
        (AES-256-CBC) and its HMAC checked with hash_equals, so a
        forged or edited cookie is rejected outright;
     2. Laravel's own cookie-value prefix (an HMAC of the cookie
        name) is verified, so a cookie from another app on the same
        key cannot be replayed here;
     3. the session id is then looked up where the ERP actually keeps
        it — a file under storage/framework/sessions, or the sessions
        table — and rejected when it has expired;
     4. only a session that carries Laravel's login key
        (login_web_<sha1 of the guard>) counts as signed in.

   Everything fails closed: any missing piece, any mismatch, any
   expiry returns null and EON falls back to asking for its token.
   Nothing here writes: EON never touches the ERP's session.
   ============================================================ */
final class ErpSession
{
    private static bool $resolved = false;
    private static ?array $user = null;
    private static array $env = [];
    private static bool $envRead = false;

    /** the ERP checkout, next to EON's own folder */
    public static function root(): ?string
    {
        $erp = dirname(EON_ROOT) . '/erp';
        return is_file($erp . '/.env') ? $erp : null;
    }

    /** one value out of the ERP's .env (read once, never cached to disk) */
    public static function env(string $key, ?string $default = null): ?string
    {
        if (!self::$envRead) {
            self::$envRead = true;
            $root = self::root();
            if ($root !== null) {
                foreach (@file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    self::$env[trim($k)] = trim(trim($v), "\"'");
                }
            }
        }
        $v = self::$env[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /** is the wiring in place at all? (used by health.php; reveals nothing secret) */
    public static function available(): bool
    {
        if (self::root() === null) return false;
        $key = (string) self::env('APP_KEY', '');
        return $key !== '' && str_starts_with($key, 'base64:');
    }

    /** the cookie the ERP sets: SESSION_COOKIE, or slug(APP_NAME)_session as Laravel derives it */
    public static function cookieName(): string
    {
        $explicit = self::env('SESSION_COOKIE');
        if ($explicit !== null) return $explicit;
        $app = (string) self::env('APP_NAME', 'laravel');
        // Str::slug($name, '_') — lower-cased, non-alphanumerics collapsed to the separator
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $app), '_'));
        return ($slug === '' ? 'laravel' : $slug) . '_session';
    }

    /** the signed-in user behind this request, or null */
    public static function user(): ?array
    {
        if (self::$resolved) return self::$user;
        self::$resolved = true;
        self::$user = null;
        try { self::$user = self::resolve(); } catch (Throwable $e) { self::$user = null; }
        return self::$user;
    }

    private static function resolve(): ?array
    {
        $root = self::root();
        if ($root === null) return null;

        $appKey = (string) self::env('APP_KEY', '');
        if (!str_starts_with($appKey, 'base64:')) return null;
        $key = base64_decode(substr($appKey, 7), true);
        if ($key === false || strlen($key) !== 32) return null;          // AES-256 needs 32 bytes

        $name = self::cookieName();
        $raw = (string) ($_COOKIE[$name] ?? '');
        if ($raw === '') return null;

        $sessionId = self::openCookie($raw, $key, $name);
        if ($sessionId === null || !preg_match('/^[A-Za-z0-9]{20,64}$/', $sessionId)) return null;

        $lifetime = max(1, (int) self::env('SESSION_LIFETIME', '120'));   // minutes
        $driver = strtolower((string) self::env('SESSION_DRIVER', 'database'));
        $payload = $driver === 'database'
            ? self::payloadFromDb($sessionId, $lifetime)
            : self::payloadFromFile($root, $sessionId, $lifetime);
        if ($payload === null) return null;

        $data = @unserialize($payload, ['allowed_classes' => false]);
        if (!is_array($data)) return null;

        // Laravel stores the signed-in id under login_<guard>_<sha1 of the guard class>
        foreach ($data as $k => $v) {
            if (!is_string($k) || !preg_match('/^login_([A-Za-z0-9_]+)_[0-9a-f]{40}$/', $k, $m)) continue;
            if (!is_int($v) && !(is_string($v) && ctype_digit($v))) continue;
            return ['id' => (int) $v, 'guard' => $m[1], 'session' => $sessionId, 'via' => $driver];
        }
        return null;                                                     // a session, but nobody signed in
    }

    /** decrypt Laravel's cookie and strip its value prefix — the whole trust boundary */
    private static function openCookie(string $raw, string $key, string $name): ?string
    {
        $json = base64_decode($raw, true);
        if ($json === false) return null;
        $p = json_decode($json, true);
        if (!is_array($p) || !isset($p['iv'], $p['value'], $p['mac'])) return null;
        if (!is_string($p['iv']) || !is_string($p['value']) || !is_string($p['mac'])) return null;

        // AEAD ciphers (GCM) authenticate through their tag; CBC carries a separate HMAC.
        // The ERP pins AES-256-CBC today; honour APP_CIPHER so a future change still works.
        $cipher = strtoupper((string) self::env('APP_CIPHER', 'AES-256-CBC'));
        $tag = isset($p['tag']) && is_string($p['tag']) && $p['tag'] !== '' ? base64_decode($p['tag'], true) : null;
        $gcm = str_contains($cipher, 'GCM') || $tag !== null;

        if (!$gcm) {
            // the MAC covers iv+value exactly as they travel, so check it before touching the cipher
            $mac = hash_hmac('sha256', $p['iv'] . $p['value'], $key);
            if (!hash_equals($mac, $p['mac'])) return null;
        }

        $iv = base64_decode($p['iv'], true);
        if ($iv === false) return null;
        if (!$gcm && strlen($iv) !== 16) return null;
        $plain = $gcm
            ? openssl_decrypt($p['value'], 'aes-256-gcm', $key, 0, $iv, (string) $tag)
            : openssl_decrypt($p['value'], 'AES-256-CBC', $key, 0, $iv);
        if (!is_string($plain) || $plain === '') return null;             // GCM: a bad tag lands here

        // Laravel 9+ prefixes the value with hash_hmac('sha1', name.'v2', key).'|'
        $prefix = hash_hmac('sha1', $name . 'v2', $key) . '|';
        if (str_starts_with($plain, $prefix)) return substr($plain, strlen($prefix));
        if (str_contains($plain, '|')) return null;                      // a prefix that is not ours → reject
        return $plain;                                                   // unprefixed (older Laravel)
    }

    private static function payloadFromFile(string $root, string $id, int $lifetimeMinutes): ?string
    {
        $file = $root . '/storage/framework/sessions/' . $id;
        if (!is_file($file)) return null;
        $age = time() - (int) @filemtime($file);
        if ($age > $lifetimeMinutes * 60) return null;                   // expired, as Laravel would judge it
        $raw = @file_get_contents($file);
        return $raw === false || $raw === '' ? null : $raw;
    }

    private static function payloadFromDb(string $id, int $lifetimeMinutes): ?string
    {
        if (!Config::dbEnabled()) return null;
        try {
            $row = Db::one(Db::erp(), 'SELECT payload, last_activity FROM sessions WHERE id = ? LIMIT 1', [$id]);
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) return null;
        if (time() - (int) ($row['last_activity'] ?? 0) > $lifetimeMinutes * 60) return null;
        $payload = (string) ($row['payload'] ?? '');
        $decoded = base64_decode($payload, true);
        return $decoded === false ? ($payload !== '' ? $payload : null) : $decoded;
    }

    /** the person's name, for the greeting — best effort, never fatal */
    public static function name(): ?string
    {
        $u = self::user();
        if ($u === null || !Config::dbEnabled()) return null;
        try {
            $row = Db::one(Db::erp(), 'SELECT name FROM users WHERE id = ? LIMIT 1', [$u['id']]);
            $n = trim((string) ($row['name'] ?? ''));
            return $n === '' ? null : $n;
        } catch (Throwable $e) {
            return null;
        }
    }
}
