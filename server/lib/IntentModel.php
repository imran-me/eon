<?php
/* ============================================================
   EON · IntentModel — EON's own trained intent model, on the server.

   Learned by tools/train-intent.mjs from the owner's 100,000 labelled
   questions (92 intents); weights in server/kb/intent-model.json. A
   question is scored against every intent in a few milliseconds: no
   Google, no quota, no network. features() must match the trainer's
   character for character.
   ============================================================ */
declare(strict_types=1);

final class IntentModel
{
    private static ?array $m = null;

    private static function model(): ?array
    {
        if (self::$m !== null) return self::$m ?: null;
        $f = dirname(__DIR__) . '/kb/intent-model.json';
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        self::$m = is_array($j['intents'] ?? null) ? $j : [];
        return self::$m ?: null;
    }

    /** the lead over the runner-up above which the model is trusted (calibrated on held-out questions by the trainer) */
    public static function confidentMargin(): float { $m = self::model(); return (float) ($m['confident_margin'] ?? 1e9); }

    /** the trainer's features: words, word pairs, letter trigrams; digits folded to # */
    public static function features(string $q): array
    {
        $t = mb_strtolower($q);
        if (class_exists('Normalizer')) $t = Normalizer::normalize($t, Normalizer::FORM_C) ?: $t;
        $t = strtr($t, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        $t = preg_replace('/\d+/', '#', $t) ?? $t;
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{M}#]+/u', $t) ?: [], fn($w) => $w !== ''));
        $f = [];
        foreach ($words as $w) {
            $f['w:' . $w] = true;
            $cs = mb_str_split('<' . $w . '>');
            for ($i = 0; $i + 3 <= count($cs); $i++) $f['c:' . $cs[$i] . $cs[$i + 1] . $cs[$i + 2]] = true;
        }
        for ($i = 0; $i + 1 < count($words); $i++) $f['b:' . $words[$i] . '_' . $words[$i + 1]] = true;
        return array_keys($f);
    }

    /**
     * The intents a question most likely means, best first:
     * [['intent' => …, 'score' => …, 'margin' => …], …] — margin is the lead over the next one.
     */
    public static function rank(string $q, int $n = 3): array
    {
        $m = self::model(); if (!$m) return [];
        $fs = self::features($q);
        $out = [];
        foreach ($m['intents'] as $intent => $w) { $s = 0.0; foreach ($fs as $f) if (isset($w[$f])) $s += $w[$f]; $out[$intent] = $s; }
        arsort($out);
        $top = array_slice($out, 0, $n + 1, true); $keys = array_keys($top); $vals = array_values($top);
        $r = [];
        for ($i = 0; $i < min($n, count($keys)); $i++) $r[] = ['intent' => $keys[$i], 'score' => round($vals[$i], 3), 'margin' => round($vals[$i] - ($vals[$i + 1] ?? 0), 3)];
        return $r;
    }
}
