<?php
declare(strict_types=1);

/* Bridge to the Python analytics service (server/py/eon.py): writes the dataset to a
   temp file, runs the command, returns the JSON. Uses config 'python.bin' (default
   python3; on Hostinger usually /usr/bin/python3). Degrades to ['ok'=>false] if
   Python is missing so the rest of EON keeps working. */
final class Py
{
    public static function bin(): string { return (string) (Config::get('python.bin') ?: (getenv('EON_PYTHON') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'))); }
    public static function available(): bool
    {
        static $ok = null; if ($ok !== null) return $ok;
        if (!function_exists('proc_open')) return $ok = false;
        $r = self::exec(['health'], null); return $ok = (bool) ($r['ok'] ?? false);
    }

    /** run: Py::run('forecast', $D, ['--company' => 2, '--months' => 3]);  plug-ins: Py::run('scenario', $D, ['--json' => json_encode($params)]) */
    public static function run(string $cmd, ?array $D, array $args = []): array
    {
        $argv = [$cmd];
        foreach ($args as $k => $v) { if ($v === null || $v === false) continue; $argv[] = $k; if ($v !== true) $argv[] = (string) $v; }
        return self::exec($argv, $D);
    }

    private static function exec(array $argv, ?array $D): array
    {
        $script = EON_ROOT . '/py/eon.py'; if (!is_file($script)) return ['ok' => false, 'error' => 'py/eon.py missing'];
        $tmp = null;
        if ($D !== null) { $dir = EON_ROOT . '/storage/cache'; $tmp = $dir . '/py-' . bin2hex(random_bytes(8)) . '.json'; file_put_contents($tmp, json_encode($D, JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod($tmp, 0600); $argv[] = '--dataset'; $argv[] = $tmp; }
        $cmd = escapeshellarg(self::bin()) . ' ' . escapeshellarg($script) . ' ' . implode(' ', array_map('escapeshellarg', $argv));
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = $_ENV + ['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'];
        $p = @proc_open($cmd, $desc, $pipes, EON_ROOT . '/py', $env);
        if (!is_resource($p)) { if ($tmp) @unlink($tmp); return ['ok' => false, 'error' => 'could not start python (' . self::bin() . ')']; }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($p); if ($tmp) @unlink($tmp);
        $j = json_decode((string) $out, true);
        if (!is_array($j)) { Log::warn('python returned non-JSON', ['code' => $code, 'err' => substr((string) $err, 0, 400)]); return ['ok' => false, 'error' => 'python failed: ' . trim(substr((string) $err, -300) ?: "exit $code")]; }
        return $j;
    }
}
