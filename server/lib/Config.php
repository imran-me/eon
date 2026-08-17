<?php
declare(strict_types=1);

final class Config
{
    private static array $cfg = [];

    public static function load(): void
    {
        $base = require EON_ROOT . '/config.example.php';
        $local = is_file(EON_ROOT . '/config.local.php') ? require EON_ROOT . '/config.local.php' : [];
        self::$cfg = array_replace_recursive($base, is_array($local) ? $local : []);
        // environment overrides (Hostinger lets you set env vars per site)
        if (($k = getenv('ANTHROPIC_API_KEY'))) self::$cfg['anthropic']['api_key'] = $k;
        if (($t = getenv('EON_TOKEN')) !== false && $t !== '') self::$cfg['token'] = $t;
        foreach (['host' => 'EON_DB_HOST', 'name' => 'EON_DB_NAME', 'user' => 'EON_DB_USER', 'pass' => 'EON_DB_PASS'] as $key => $env) {
            if (($v = getenv($env)) !== false && $v !== '') { self::$cfg['db'][$key] = $v; self::$cfg['db']['enabled'] = true; }
        }
    }

    /** dotted access: Config::get('db.host', default) */
    public static function get(string $path, mixed $default = null): mixed
    {
        $node = self::$cfg;
        foreach (explode('.', $path) as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) return $default;
            $node = $node[$p];
        }
        return $node;
    }

    public static function all(): array { return self::$cfg; }
    public static function dbEnabled(): bool { return (bool) self::get('db.enabled', false) && self::get('db.name'); }
    public static function llmEnabled(): bool { return (string) self::get('anthropic.api_key', '') !== '' && class_exists('Anthropic\\Client'); }
    public static function llmKeyPresent(): bool { return (string) self::get('anthropic.api_key', '') !== ''; }
}
