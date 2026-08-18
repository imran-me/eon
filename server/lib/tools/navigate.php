<?php
/* ============================================================
   EON · tool plug-in — open one particular record.

   explain_erp (lib/Tools.php) already tells the model where a screen
   is and what it can do. This adds the one thing it cannot: the
   address of a single record — journal 1204, invoice 4821, employee
   206 — with the role segment filled in for whoever is asking, so the
   link works when the boss clicks it.

   It refuses to guess: when this ERP has no page for that kind of
   record (leads only have sub-views), it says so instead of sending
   the boss to a plausible-looking address that is not the thing.
   ============================================================ */
declare(strict_types=1);

return [
    'definitions' => [
        [
            'name' => 'erp_open_record',
            'description' => 'The exact address of ONE record when the boss names a thing and its number — "open journal 1204", "show invoice 4821", "employee 206", "task 88". Returns the URL with the signed-in role segment already filled in. Returns found=false when the ERP has no page for that kind of record; then offer the list screen (explain_erp) rather than inventing a link.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'entity' => ['type' => 'string', 'description' => 'journal, invoice, employee, task, project, customer, account, expense…'],
                    'id' => ['type' => 'string', 'description' => 'the record id or number as the boss said it'],
                ],
                'required' => ['entity', 'id'],
            ],
        ],
    ],

    'run' => function (string $name, array $in, Tools $tools, array $D, ?int $company): array {
        if ($name !== 'erp_open_record') return ['error' => "unknown tool $name"];
        if (!class_exists('ErpMap') || !ErpMap::available()) {
            return ['error' => 'the ERP map is not on this host (tools/erp-map.mjs); do not invent an address'];
        }
        $entity = strtolower(trim((string) ($in['entity'] ?? '')));
        $id = trim((string) ($in['id'] ?? ''));
        if ($entity === '' || $id === '') return ['error' => 'entity and id are required'];
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id)) return ['error' => 'that does not look like a record id'];

        // the words the boss uses are not always the ERP's own
        $alias = ['employee' => 'user', 'staff' => 'user', 'payslip' => 'payslips', 'bill' => 'expense',
            'voucher' => 'journal', 'supplier' => 'vendor', 'salary' => 'employee-salaries'];
        $words = array_unique(array_filter([$entity, $alias[$entity] ?? null]));
        $singular = fn(string $x) => (string) preg_replace(['/ies$/', '/s$/'], ['y', ''], $x);

        $best = null;
        foreach ((ErpMap::load()['details'] ?? []) as $d) {
            $segs = array_values(array_filter(explode('/', (string) ($d['uri'] ?? '')), fn($p) => $p !== '' && $p !== '{role}'));
            $res = $segs[0] ?? '';
            $score = 0.0;
            foreach ($words as $w) {
                if ($res === $w || $res === $w . 's' || $singular($res) === $singular($w)) $score += 4;
            }
            if ($score <= 0) continue;                                   // must be that entity's own screen
            $route = (string) ($d['name'] ?? '');
            if (str_ends_with($route, '.show')) $score += 3;
            elseif (str_ends_with($route, '.edit')) $score += 0.5;
            if (preg_match('/\.(destroy|update|store|print|download)$/', $route)) $score -= 3;
            $after = explode('{' . ($d['param'] ?? 'id') . '}', (string) $d['uri'])[1] ?? '';
            if (trim($after, '/') !== '') $score -= 2;                    // a sub-view, not the record
            if (!$best || $score > $best['score']) $best = ['score' => $score, 'd' => $d];
        }

        if (!$best || $best['score'] < 2.5) {
            return ['found' => false, 'entity' => $entity, 'note' => "this ERP has no page for a single {$entity} — offer the list screen instead"];
        }

        $d = $best['d'];
        $role = 'super-admin';
        try {
            $u = class_exists('ErpSession') ? ErpSession::user() : null;
            if ($u && Config::dbEnabled()) {
                $row = Db::one(Db::erp(), 'SELECT r.name FROM roles r JOIN model_has_roles m ON m.role_id = r.id WHERE m.model_id = ? LIMIT 1', [$u['id']]);
                $n = trim((string) ($row['name'] ?? ''));
                if ($n !== '') $role = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $n));
            }
        } catch (Throwable $e) { /* the default role segment still gives a working link */ }

        $uri = str_replace(['{' . ($d['param'] ?? 'id') . '}', '{role}'], [$id, $role], (string) $d['uri']);
        return ['found' => true, 'entity' => $entity, 'id' => $id, 'url' => $uri, 'route' => $d['name'] ?? null, 'role' => $role];
    },
];
