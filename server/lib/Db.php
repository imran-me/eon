<?php
declare(strict_types=1);

/* PDO wrappers: the ERP database (read-only) and EON's own tables. */
final class Db
{
    private static ?PDO $erp = null;
    private static ?PDO $eon = null;

    public static function erp(): PDO
    {
        if (self::$erp) return self::$erp;
        if (!Config::dbEnabled()) throw new RuntimeException('ERP database not configured (server/config.local.php → db)');
        $c = Config::get('db');
        self::$erp = self::connect($c);
        return self::$erp;
    }

    public static function eon(): PDO
    {
        if (self::$eon) return self::$eon;
        $c = Config::get('eon_db') ?: Config::get('db');
        if (!($c['enabled'] ?? false) && !Config::get('eon_db')) throw new RuntimeException('EON database not configured');
        self::$eon = self::connect($c);
        return self::$eon;
    }

    private static function connect(array $c): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'] ?? '127.0.0.1', (int) ($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return $pdo;
    }

    public static function available(): bool
    {
        try { self::erp()->query('SELECT 1'); return true; } catch (Throwable $e) { return false; }
    }

    public static function eonAvailable(): bool
    {
        try { self::eon()->query('SELECT 1 FROM eon_settings LIMIT 1'); return true; } catch (Throwable $e) { return false; }
    }

    /** SELECT helper */
    public static function rows(PDO $pdo, string $sql, array $params = []): array
    {
        $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll();
    }
    public static function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $st = $pdo->prepare($sql); $st->execute($params); $r = $st->fetch(); return $r === false ? null : $r;
    }
    public static function tableExists(PDO $pdo, string $table): bool
    {
        try { $r = self::one($pdo, 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]); return (bool) $r; } catch (Throwable $e) { return false; }
    }
    public static function columns(PDO $pdo, string $table): array
    {
        try { return array_column(self::rows($pdo, 'SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?', [$table]), 'c'); } catch (Throwable $e) { return []; }
    }
}
