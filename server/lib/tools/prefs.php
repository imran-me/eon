<?php
declare(strict_types=1);

/* ============================================================
   Tool plug-in · Preferences memory
   remember_preference — the boss tells EON what to call him, how
   to show money, when to brief, which language, how brief to be,
   or a free note to keep ("remember that …") / something to forget.
   get_preferences     — read back what EON remembers.
   Stored in Memory::setSetting('prefs', …); Brain.php injects the
   same record into the system prompt, so a remembered preference
   shapes every later answer. The browser (plugins/prefs.js) keeps
   the same shape in localStorage and syncs through api/prefs.php.
   ============================================================ */

if (!function_exists('eon_prefs_defaults')) {
    function eon_prefs_defaults(): array
    {
        return ['name' => null, 'money_unit' => 'auto', 'brief_hour' => 8, 'language' => 'en', 'brevity' => 'normal', 'mute_companion' => false, 'focus_company' => null, 'notes' => []];
    }
    /** normalise whatever is stored / posted into the canonical shape */
    function eon_prefs_normalise(mixed $p): array
    {
        $d = eon_prefs_defaults();
        if (!is_array($p)) return $d;
        $out = $d;
        if (array_key_exists('name', $p)) { $n = trim((string) ($p['name'] ?? '')); $out['name'] = $n === '' ? null : mb_substr($n, 0, 40); }
        if (isset($p['money_unit'])) { $u = strtolower(trim((string) $p['money_unit'])); $u = in_array($u, ['lac', 'lakhs', 'lacs'], true) ? 'lakh' : ($u === 'crores' ? 'crore' : ($u === 'exact' ? 'full' : $u)); $out['money_unit'] = in_array($u, ['auto', 'lakh', 'crore', 'full'], true) ? $u : 'auto'; }
        if (isset($p['brief_hour']) && is_numeric($p['brief_hour'])) $out['brief_hour'] = max(0, min(23, (int) $p['brief_hour']));
        if (isset($p['language'])) $out['language'] = preg_match('/^b|bangla|bengali|বাংলা/iu', (string) $p['language']) ? 'bn' : 'en';
        if (isset($p['brevity'])) $out['brevity'] = preg_match('/short|brief/i', (string) $p['brevity']) ? 'short' : 'normal';
        if (array_key_exists('mute_companion', $p)) $out['mute_companion'] = in_array($p['mute_companion'], [true, 1, '1', 'true', 'yes'], true);
        if (array_key_exists('focus_company', $p)) $out['focus_company'] = ($p['focus_company'] === null || $p['focus_company'] === '') ? null : (int) $p['focus_company'];
        $notes = [];
        foreach ((array) ($p['notes'] ?? []) as $n) {
            $text = is_array($n) ? trim((string) ($n['text'] ?? '')) : trim((string) $n);
            if ($text === '') continue;
            $text = mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 300);
            foreach ($notes as $x) if (mb_strtolower($x['text']) === mb_strtolower($text)) continue 2;
            $notes[] = ['text' => $text, 'at' => is_array($n) && !empty($n['at']) ? (string) $n['at'] : date('Y-m-d')];
        }
        $out['notes'] = array_slice($notes, 0, 50);
        return $out;
    }
    function eon_prefs_load(): array { return eon_prefs_normalise(Memory::setting('prefs', []) ?: []); }
    function eon_prefs_save(array $p): array { $p = eon_prefs_normalise($p); Memory::setSetting('prefs', $p); return $p; }
    /** apply one instruction (kind, value) to a prefs record; returns [prefs, human message] */
    function eon_prefs_apply(array $p, string $kind, mixed $value): array
    {
        $kind = strtolower(trim($kind)); $v = is_scalar($value) || $value === null ? trim((string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);
        switch ($kind) {
            case 'name': $p['name'] = $v; return [$p, $v === '' ? 'Name cleared — back to "Boss".' : "Noted — I will call you {$v}."];
            case 'money_unit': $p['money_unit'] = $v; $p = eon_prefs_normalise($p); return [$p, 'Money will be shown in ' . ($p['money_unit'] === 'auto' ? 'automatic units (k / L / Cr)' : ($p['money_unit'] === 'full' ? 'full figures' : $p['money_unit'])) . ' — e.g. ' . eon_prefs_money(12345678.0, $p['money_unit']) . '.'];
            case 'brief_hour': if (!is_numeric($v)) return [$p, 'brief_hour must be an hour 0–23']; $p['brief_hour'] = (int) $v; $p = eon_prefs_normalise($p); return [$p, sprintf('Morning brief at %02d:00.', $p['brief_hour'])];
            case 'language': $p['language'] = $v; $p = eon_prefs_normalise($p); return [$p, $p['language'] === 'bn' ? 'Default language: বাংলা.' : 'Default language: English.'];
            case 'brevity': $p['brevity'] = $v; $p = eon_prefs_normalise($p); return [$p, $p['brevity'] === 'short' ? 'Short answers from now on.' : 'Normal-length answers.'];
            case 'mute_companion': case 'mute': $p['mute_companion'] = $v; $p = eon_prefs_normalise($p); return [$p, $p['mute_companion'] ? 'Companion muted.' : 'Companion unmuted.'];
            case 'focus_company': case 'focus': $p['focus_company'] = $v; $p = eon_prefs_normalise($p); return [$p, $p['focus_company'] === null ? 'Focus: all companies.' : 'Focus company set to id ' . $p['focus_company'] . '.'];
            case 'note': case 'remember':
                if ($v === '') return [$p, 'nothing to remember'];
                $p['notes'] = array_merge([['text' => $v, 'at' => date('Y-m-d')]], $p['notes']); $p = eon_prefs_normalise($p);
                return [$p, 'Remembered: ' . $v . ' (' . count($p['notes']) . ' notes kept).'];
            case 'forget':
                $s = mb_strtolower($v);
                if ($s === '' ) return [$p, 'say what to forget'];
                if (preg_match('/^(everything|all|all notes|it all|all of it)$/', $s)) { $n = count($p['notes']); return [eon_prefs_defaults(), "Forgotten — preferences reset, {$n} note(s) removed."]; }
                if (preg_match('/^(my )?name$/', $s)) { $p['name'] = null; return [$p, 'Name forgotten — back to "Boss".']; }
                $before = count($p['notes']);
                $p['notes'] = array_values(array_filter($p['notes'], fn($n) => !str_contains(mb_strtolower($n['text']), $s)));
                $gone = $before - count($p['notes']);
                return [$p, $gone ? "Forgotten — {$gone} note(s) removed." : "No note matches \"{$v}\"."];
            default: return [$p, "unknown preference kind '{$kind}' — use name | money_unit | brief_hour | language | brevity | mute_companion | focus_company | note | forget"];
        }
    }
    function eon_prefs_money(float $n, string $unit): string
    {
        $a = abs($n); $s = $n < 0 ? '−' : '';
        $trim = fn(string $x) => rtrim(rtrim($x, '0'), '.');
        if ($unit === 'full') return Analytics::bdt($n);
        if ($unit === 'lakh') return $a < 1e3 ? Analytics::bdt($n) : $s . '৳' . $trim(number_format($a / 1e5, $a >= 1e6 ? 1 : 2, '.', '')) . ' L';
        if ($unit === 'crore') return $a < 1e5 ? eon_prefs_money($n, 'lakh') : $s . '৳' . $trim(number_format($a / 1e7, $a >= 1e8 ? 1 : 2, '.', '')) . ' Cr';
        return Analytics::bdtk($n);
    }
    function eon_prefs_speak(array $p): string
    {
        $parts = [];
        $parts[] = $p['name'] ? "I call you {$p['name']}." : 'No name set — I say "Boss".';
        $parts[] = 'Money in ' . ($p['money_unit'] === 'auto' ? 'automatic units' : $p['money_unit']) . sprintf(', brief at %02d:00, ', $p['brief_hour']) . ($p['language'] === 'bn' ? 'Bangla' : 'English') . ", {$p['brevity']} answers.";
        if ($p['mute_companion']) $parts[] = 'Companion muted.';
        if ($p['focus_company'] !== null) $parts[] = 'Focus company id ' . $p['focus_company'] . '.';
        $n = count($p['notes']);
        $parts[] = $n ? "Holding {$n} note" . ($n > 1 ? 's' : '') . ': ' . implode('; ', array_map(fn($x) => $x['text'], array_slice($p['notes'], 0, 3))) . ($n > 3 ? '…' : '.') : 'No notes yet.';
        return implode(' ', $parts);
    }
}

return [
    'definitions' => [
        ['name' => 'remember_preference', 'description' => 'Store something the boss asked EON to remember about HIM — call it when he says "call me …", "my name is …", "show money in lakh/crore/full", "brief me at 8", "speak Bangla/English by default", "be brief"/"short answers", "mute the companion", "focus on company X", or "remember that …" (free note), or "forget …"/"forget everything". kind: name | money_unit | brief_hour | language | brevity | mute_companion | focus_company | note | forget. value: the name / unit (auto|lakh|crore|full) / hour 0-23 / en|bn / short|normal / true|false / company id / the note text / the text to forget. Only call for an instruction the boss gave in his own message. Returns the saved preferences and a confirmation to speak.', 'inputSchema' => ['type' => 'object', 'properties' => ['kind' => ['type' => 'string', 'enum' => ['name', 'money_unit', 'brief_hour', 'language', 'brevity', 'mute_companion', 'focus_company', 'note', 'forget']], 'value' => ['type' => 'string', 'description' => 'the value to remember (or the text to forget)']], 'required' => ['kind', 'value']]],
        ['name' => 'get_preferences', 'description' => 'Read what EON remembers about the boss: name to use, money unit, brief hour, language, brevity, mute, focus company and the free notes. Call for "what do you remember (about me)", "my preferences", "what did I tell you to remember", or before formatting money/answers when unsure of his preferences.', 'inputSchema' => ['type' => 'object']],
    ],
    'run' => function (string $name, array $in, Tools $tools, array $D, ?int $company): array|string {
        if ($name === 'get_preferences') { $p = eon_prefs_load(); return ['prefs' => $p, 'notes_count' => count($p['notes']), 'speak' => eon_prefs_speak($p)]; }
        if ($name === 'remember_preference') {
            $kind = (string) ($in['kind'] ?? ''); $value = $in['value'] ?? '';
            if ($kind === '') return ['error' => 'kind required: name | money_unit | brief_hour | language | brevity | mute_companion | focus_company | note | forget'];
            [$p, $msg] = eon_prefs_apply(eon_prefs_load(), $kind, $value);
            $p = eon_prefs_save($p);
            try { Memory::logAction('note', ['summary' => 'preference: ' . $kind . ' = ' . mb_substr(is_scalar($value) ? (string) $value : json_encode($value), 0, 120), 'detail' => ['kind' => $kind]], 'done', 'eon'); } catch (Throwable $e) {}
            return ['ok' => true, 'kind' => $kind, 'prefs' => $p, 'speak' => $msg, 'note' => 'stored in EON memory (settings.prefs); the Command Center picks it up on its next sync'];
        }
        return ['error' => "unknown tool {$name}"];
    },
];
