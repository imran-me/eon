<?php
declare(strict_types=1);

/* ============================================================
   ErpMap — EON knows the ERP it lives in.

   tools/erp-map.mjs reads the ERP's own source and writes
   ai-companion/eon-brain/domains/erp/erp-map.json: every route and
   page with its address, the sidebar the way the boss reads it,
   what each controller can do, every model and its table, and all
   203 real tables with their columns.

   The browser plug-in reads that file; so does this class, so the
   language model can answer "where do I issue a payslip", "what can
   I do on the leads screen" and "which table holds ticket sales"
   from the ERP's own source rather than from guesswork.
   ============================================================ */
final class ErpMap
{
    private static ?array $map = null;
    private static bool $tried = false;

    public static function path(): string
    {
        return EON_ROOT . '/../ai-companion/eon-brain/domains/erp/erp-map.json';
    }

    public static function load(): array
    {
        if (self::$tried) return self::$map ?? [];
        self::$tried = true;
        $f = self::path();
        if (is_file($f)) {
            $d = json_decode((string) file_get_contents($f), true);
            if (is_array($d)) self::$map = $d;
        }
        return self::$map ?? [];
    }

    public static function available(): bool { return self::load() !== []; }

    public static function meta(): array { return self::load()['meta'] ?? []; }

    /** one line the system prompt can carry: how big the ERP is */
    public static function summary(): string
    {
        $m = self::meta();
        if (!$m) return '';
        return sprintf(
            'The ERP EON runs inside: %d routes over %d screens, %d controllers, %d models, %d database tables, %d sidebar entries (map generated %s).',
            (int) ($m['routes'] ?? 0), (int) ($m['pages'] ?? 0), (int) ($m['controllers'] ?? 0),
            (int) ($m['models'] ?? 0), (int) ($m['tables'] ?? 0), (int) ($m['menu_items'] ?? 0),
            (string) ($m['generated_at'] ?? '')
        );
    }

    /** the menu the boss actually sees, as an indented outline (for the system prompt) */
    public static function menuOutline(int $limit = 120): string
    {
        $map = self::load();
        $out = []; $seen = [];
        foreach (($map['menu'] ?? []) as $m) {
            $label = trim((string) ($m['label'] ?? ''));
            if ($label === '' || $label === '#') continue;
            $sec = trim((string) ($m['section'] ?? ''));
            $key = $sec . '|' . $label;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ($sec !== '' ? $sec . ' › ' : '') . $label . (($m['href'] ?? '') && $m['href'] !== '#' ? '  ' . $m['href'] : '');
            if (count($out) >= $limit) break;
        }
        return implode("\n", $out);
    }

    /* ---------- lookups ---------- */

    private static function score(string $needle, string $hay): int
    {
        $needle = mb_strtolower(trim($needle)); $hay = mb_strtolower($hay);
        if ($needle === '' || $hay === '') return 0;
        if ($hay === $needle) return 100;
        if (str_contains($hay, $needle)) return 60 - min(30, mb_strlen($hay) - mb_strlen($needle));
        $n = 0;
        foreach (preg_split('/[\s_\-\/]+/', $needle) ?: [] as $w) if ($w !== '' && str_contains($hay, $w)) $n += 12;
        return $n;
    }

    /** find screens by a human phrase: "payslip", "journal entry", "leads" */
    public static function findPages(string $q, int $limit = 8): array
    {
        $map = self::load(); $hits = [];
        foreach (($map['pages'] ?? []) as $p) {
            $s = max(self::score($q, (string) ($p['name'] ?? '')), self::score($q, (string) ($p['uri'] ?? '')), self::score($q, (string) ($p['controller'] ?? '')));
            if ($s > 0) $hits[] = ['score' => $s, 'uri' => $p['uri'] ?? '', 'name' => $p['name'] ?? '', 'controller' => $p['controller'] ?? '', 'action' => $p['action'] ?? ''];
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, $limit);
    }

    /** what a screen/module can do — the controller's actions */
    public static function findControllers(string $q, int $limit = 4): array
    {
        $map = self::load(); $hits = [];
        foreach (($map['controllers'] ?? []) as $c) {
            $s = self::score($q, (string) ($c['controller'] ?? ''));
            if ($s > 0) $hits[] = ['score' => $s, 'controller' => $c['controller'], 'actions' => array_slice((array) ($c['actions'] ?? []), 0, 30)];
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, $limit);
    }

    /** which table holds what, and its columns */
    public static function findTables(string $q, int $limit = 5): array
    {
        $map = self::load(); $hits = [];
        foreach (($map['tables'] ?? []) as $t) {
            $s = self::score($q, (string) ($t['table'] ?? ''));
            if ($s > 0) $hits[] = ['score' => $s, 'table' => $t['table'], 'columns' => array_map(fn($c) => is_array($c) ? ($c['name'] ?? '') : $c, array_slice((array) ($t['columns'] ?? []), 0, 40))];
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, $limit);
    }

    public static function findModels(string $q, int $limit = 5): array
    {
        $map = self::load(); $hits = [];
        foreach (($map['models'] ?? []) as $m) {
            $s = max(self::score($q, (string) ($m['model'] ?? '')), self::score($q, (string) ($m['table'] ?? '')));
            if ($s > 0) $hits[] = ['score' => $s, 'model' => $m['model'], 'table' => $m['table'] ?? null, 'fields' => array_slice((array) ($m['fields'] ?? []), 0, 30), 'relations' => array_slice((array) ($m['relations'] ?? []), 0, 20)];
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, $limit);
    }

    /** one answer for "where is X / what can I do on X / which table holds X" */
    public static function explain(string $q): array
    {
        if (!self::available()) return ['error' => 'the ERP map has not been generated on this host (tools/erp-map.mjs)'];
        return [
            'query' => $q,
            'screens' => self::findPages($q),
            'can_do' => self::findControllers($q),
            'tables' => self::findTables($q),
            'models' => self::findModels($q),
            'note' => 'Screen addresses live under the signed-in role segment, e.g. /super-admin{uri} or /accountant{uri}.',
        ];
    }
}
