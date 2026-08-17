<?php
declare(strict_types=1);

final class Log
{
    public static function write(string $level, string $msg, array $ctx = []): void
    {
        $file = Config::get('log') ?: EON_ROOT . '/storage/logs/eon.log';
        $line = sprintf("[%s] %s %s %s\n", date('c'), strtoupper($level), $msg, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
    public static function info(string $m, array $c = []): void { self::write('info', $m, $c); }
    public static function warn(string $m, array $c = []): void { self::write('warn', $m, $c); }
    public static function error(string $m, array $c = []): void { self::write('error', $m, $c); }
}
