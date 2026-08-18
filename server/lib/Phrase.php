<?php
declare(strict_types=1);

/* ============================================================
   Phrase — how EON *sounds*.

   Two native voices, not one voice translated:
     en  an executive aide speaking plain, warm, unfussy English
     bn  a Dhaka office assistant speaking real spoken Bangla —
         লাখ/কোটি, Bengali numerals, স্যার, and the little
         connectives a person actually uses (তবে, আসলে, একটু)

   Everything here is deterministic per question (crc32 seed), so
   the same question sounds the same twice, but two different
   questions do not sound like the same template.
   ============================================================ */
final class Phrase
{
    private static string $seed = '';

    /** call once per answer so the variation is stable for that question */
    public static function seed(string $s): void
    {
        self::$seed = $s;
    }

    /** deterministic pick from a list of equivalent phrasings */
    public static function pick(array $variants, string $salt = ''): string
    {
        if (!$variants) return '';
        $i = crc32(self::$seed . '|' . $salt) % count($variants);
        return $variants[$i];
    }

    /* ---------------- numbers ---------------- */

    private const BN_DIGIT = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
                              '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    public static function bnDigits(string $s): string
    {
        return strtr($s, self::BN_DIGIT);
    }

    /** Bangladeshi grouping: 12,34,567 */
    public static function group(float $n): string
    {
        $neg = $n < 0;
        $n = abs($n);
        $whole = (string) (int) round($n);
        if (strlen($whole) <= 3) return ($neg ? '-' : '') . $whole;
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/(\d)(?=(\d\d)+$)/', '$1,', $rest);
        return ($neg ? '-' : '') . $rest . ',' . $last3;
    }

    /**
     * Money the way each language says it.
     *   en: ৳1.9 L · ৳3.4 Cr · ৳4,250
     *   bn: ৳১.৯ লাখ · ৳৩.৪ কোটি · ৳৪,২৫০
     */
    public static function money(float $n, string $lang = 'en', bool $exact = false): string
    {
        $neg = $n < 0;
        $a = abs($n);
        $sign = $neg ? '−' : '';

        if ($exact || $a < 1000) {
            $s = '৳' . self::group($a);
            return $sign . ($lang === 'bn' ? self::bnDigits($s) : $s);
        }
        if ($a >= 1e7) {
            $v = self::trim($a / 1e7);
            $s = '৳' . $v . ($lang === 'bn' ? ' কোটি' : ' Cr');
        } elseif ($a >= 1e5) {
            $v = self::trim($a / 1e5);
            $s = '৳' . $v . ($lang === 'bn' ? ' লাখ' : ' L');
        } elseif ($a >= 1000) {
            $v = self::trim($a / 1000);
            $s = '৳' . $v . ($lang === 'bn' ? ' হাজার' : 'k');
        } else {
            $s = '৳' . self::group($a);
        }
        return $sign . ($lang === 'bn' ? self::bnDigits($s) : $s);
    }

    private static function trim(float $v): string
    {
        $s = number_format($v, ($v < 10 ? 2 : 1), '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }

    /** a plain count, in the reader's numerals */
    public static function n($v, string $lang = 'en'): string
    {
        $s = is_float($v) ? self::trim($v) : (string) (int) $v;
        return $lang === 'bn' ? self::bnDigits($s) : $s;
    }

    public static function pct(float $v, string $lang = 'en'): string
    {
        $s = self::trim($v) . '%';
        return $lang === 'bn' ? self::bnDigits($s) : $s;
    }

    /* ---------------- dates ---------------- */

    private const MONTH_BN = [1 => 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন',
        'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    /** "July 2026" / "জুলাই ২০২৬" from a Y-m key */
    public static function monthName(string $ym, string $lang = 'en'): string
    {
        $parts = explode('-', $ym);
        $m = (int) ($parts[1] ?? 0);
        $y = (int) ($parts[0] ?? 0);
        if ($m < 1 || $m > 12) return $ym;
        if ($lang === 'bn') return self::MONTH_BN[$m] . ' ' . self::bnDigits((string) $y);
        return date('F Y', mktime(0, 0, 0, $m, 1, $y));
    }

    /** "19 August" / "১৯ আগস্ট" */
    public static function day(string $date, string $lang = 'en'): string
    {
        $t = strtotime($date);
        if (!$t) return $date;
        if ($lang === 'bn') return self::bnDigits(date('j', $t)) . ' ' . self::MONTH_BN[(int) date('n', $t)];
        return date('j F', $t);
    }

    /* ---------------- joining ---------------- */

    /** "A, B and C" / "A, B আর C" */
    public static function join(array $items, string $lang = 'en'): string
    {
        $items = array_values(array_filter(array_map('strval', $items), fn($x) => $x !== ''));
        $c = count($items);
        if ($c === 0) return '';
        if ($c === 1) return $items[0];
        $last = array_pop($items);
        return implode(', ', $items) . ($lang === 'bn' ? ' আর ' : ' and ') . $last;
    }

    /** "1 person" / "3 people" — English only; Bangla nouns do not inflect for number */
    public static function plural(int $n, string $one, string $many): string
    {
        return $n === 1 ? $one : $many;
    }

    /* ---------------- tone ----------------
       mood: good | ok | warn | bad          */

    /** an opener that sets the temperature before the number lands */
    public static function opener(string $mood, string $lang, string $salt = ''): string
    {
        $en = [
            'good' => ['Comfortable — ', 'Good news: ', 'This one is healthy: ', 'No trouble here — ', ''],
            'ok'   => ['', 'Here it is: ', 'Short version: ', 'As it stands, '],
            'warn' => ['Worth a look — ', 'One to watch: ', 'Keep half an eye on this: ', 'Not urgent, but — '],
            'bad'  => ['This needs you: ', 'Careful here — ', 'Straight up: ', 'I would act on this today: '],
        ];
        $bn = [
            'good' => ['ভালো খবর — ', 'চিন্তার কিছু নেই — ', 'এদিকটা ঠিক আছে: ', ''],
            'ok'   => ['', 'সংক্ষেপে বলি — ', 'এই মুহূর্তে ', 'অবস্থাটা এই: '],
            'warn' => ['একটু খেয়াল রাখতে হবে — ', 'এদিকটা দেখা দরকার: ', 'জরুরি না, তবে — ', 'একটা বিষয় বলি — '],
            'bad'  => ['এটা আপনার নজর দরকার: ', 'সাবধান হতে হবে — ', 'সরাসরি বলি — ', 'আজই একটা সিদ্ধান্ত দরকার: '],
        ];
        $set = $lang === 'bn' ? $bn : $en;
        return self::pick($set[$mood] ?? $set['ok'], 'open' . $salt);
    }

    /** the recommended action, introduced like a person would */
    public static function advise(string $action, string $lang, string $salt = ''): string
    {
        if ($action === '') return '';
        $lead = $lang === 'bn'
            ? self::pick(['আমার পরামর্শ — ', 'যা করা উচিত: ', 'পরবর্তী পদক্ষেপ: ', 'আমি বলব — '], 'adv' . $salt)
            : self::pick(['My call: ', 'What I would do: ', 'Next step: ', 'Suggestion — '], 'adv' . $salt);
        return $lead . $action;
    }

    /** how EON addresses the boss, honouring the remembered preference */
    public static function address(string $lang, ?string $name = null): string
    {
        if ($name !== null && $name !== '') return $name;
        return $lang === 'bn' ? 'স্যার' : '';
    }

    /** stitch sentences without double spaces or stray punctuation */
    public static function sentence(array $parts): string
    {
        $out = '';
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p === '') continue;
            if ($out !== '' && !preg_match('/[—:\-]\s*$/u', $out)) $out .= ' ';
            $out .= $p;
        }
        $out = preg_replace('/\s+/u', ' ', $out);
        $out = preg_replace('/\s+([.,;:।])/u', '$1', (string) $out);
        return trim((string) $out);
    }

    /** mood from a ratio where higher is better (e.g. present %) */
    public static function moodHigh(float $v, float $good, float $warn): string
    {
        if ($v >= $good) return 'good';
        if ($v >= $warn) return 'ok';
        return $v >= $warn * 0.6 ? 'warn' : 'bad';
    }

    /** mood from a count where lower is better (e.g. overdue items) */
    public static function moodLow(float $v, float $good, float $warn): string
    {
        if ($v <= $good) return 'good';
        if ($v <= $warn) return 'warn';
        return 'bad';
    }
}
