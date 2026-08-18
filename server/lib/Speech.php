<?php
declare(strict_types=1);

/* ============================================================
   Speech — the difference between text EON writes and words
   EON says.

   On screen "৳১.৮৯ লাখ" is exactly right. Read aloud by a
   speech engine it comes out "এক দশমিক আট নয় লাখ", which no
   Bangladeshi has ever said. A person says
   "এক লাখ ঊননব্বই হাজার টাকা".

   So the spoken form is built, not stripped:
     · money and percentages become words, rounded the way a
       person rounds them
     · account codes are read digit by digit, because 1011 is a
       code and not a thousand and eleven
     · URLs and menu arrows are never read out — "HR → Payslips"
       becomes "এইচআর মেনুতে পে-স্লিপ"
     · em dashes and brackets become the pauses they stand for
     · the current year is left unsaid, as anyone would

   Everything degrades safely: an unrecognised fragment is
   returned as it was rather than mangled.
   ============================================================ */
final class Speech
{
    /** placed where an approximation is introduced, then collapsed once at the end
        so "প্রায় ০.৯ মাস" never becomes "প্রায় প্রায় এক মাস" */
    private const APPROX = "\u{2063}";

    /* ---------------- Bangla numbers ----------------
       Bangla has an irregular word for every number to 99, so
       there is no rule to derive them from — the table is the rule. */
    private const BN_1_99 = [
        0 => 'শূন্য', 1 => 'এক', 2 => 'দুই', 3 => 'তিন', 4 => 'চার', 5 => 'পাঁচ', 6 => 'ছয়',
        7 => 'সাত', 8 => 'আট', 9 => 'নয়', 10 => 'দশ', 11 => 'এগারো', 12 => 'বারো', 13 => 'তেরো',
        14 => 'চোদ্দ', 15 => 'পনেরো', 16 => 'ষোলো', 17 => 'সতেরো', 18 => 'আঠারো', 19 => 'উনিশ',
        20 => 'বিশ', 21 => 'একুশ', 22 => 'বাইশ', 23 => 'তেইশ', 24 => 'চব্বিশ', 25 => 'পঁচিশ',
        26 => 'ছাব্বিশ', 27 => 'সাতাশ', 28 => 'আটাশ', 29 => 'ঊনত্রিশ', 30 => 'ত্রিশ',
        31 => 'একত্রিশ', 32 => 'বত্রিশ', 33 => 'তেত্রিশ', 34 => 'চৌত্রিশ', 35 => 'পঁয়ত্রিশ',
        36 => 'ছত্রিশ', 37 => 'সাঁইত্রিশ', 38 => 'আটত্রিশ', 39 => 'ঊনচল্লিশ', 40 => 'চল্লিশ',
        41 => 'একচল্লিশ', 42 => 'বিয়াল্লিশ', 43 => 'তেতাল্লিশ', 44 => 'চুয়াল্লিশ', 45 => 'পঁয়তাল্লিশ',
        46 => 'ছেচল্লিশ', 47 => 'সাতচল্লিশ', 48 => 'আটচল্লিশ', 49 => 'ঊনপঞ্চাশ', 50 => 'পঞ্চাশ',
        51 => 'একান্ন', 52 => 'বায়ান্ন', 53 => 'তিপ্পান্ন', 54 => 'চুয়ান্ন', 55 => 'পঞ্চান্ন',
        56 => 'ছাপ্পান্ন', 57 => 'সাতান্ন', 58 => 'আটান্ন', 59 => 'ঊনষাট', 60 => 'ষাট',
        61 => 'একষট্টি', 62 => 'বাষট্টি', 63 => 'তেষট্টি', 64 => 'চৌষট্টি', 65 => 'পঁয়ষট্টি',
        66 => 'ছেষট্টি', 67 => 'সাতষট্টি', 68 => 'আটষট্টি', 69 => 'ঊনসত্তর', 70 => 'সত্তর',
        71 => 'একাত্তর', 72 => 'বাহাত্তর', 73 => 'তিয়াত্তর', 74 => 'চুয়াত্তর', 75 => 'পঁচাত্তর',
        76 => 'ছিয়াত্তর', 77 => 'সাতাত্তর', 78 => 'আটাত্তর', 79 => 'ঊনআশি', 80 => 'আশি',
        81 => 'একাশি', 82 => 'বিরাশি', 83 => 'তিরাশি', 84 => 'চুরাশি', 85 => 'পঁচাশি',
        86 => 'ছিয়াশি', 87 => 'সাতাশি', 88 => 'আটাশি', 89 => 'ঊননব্বই', 90 => 'নব্বই',
        91 => 'একানব্বই', 92 => 'বিরানব্বই', 93 => 'তিরানব্বই', 94 => 'চুরানব্বই', 95 => 'পঁচানব্বই',
        96 => 'ছিয়ানব্বই', 97 => 'সাতানব্বই', 98 => 'আটানব্বই', 99 => 'নিরানব্বই',
    ];

    private const EN_1_19 = [0 => 'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven',
        'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'];
    private const EN_TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    /** 0–99 in words */
    private static function under100(int $n, string $lang): string
    {
        if ($lang === 'bn') return self::BN_1_99[$n] ?? (string) $n;
        if ($n < 20) return self::EN_1_19[$n];
        $t = intdiv($n, 10);
        $u = $n % 10;
        return self::EN_TENS[$t] . ($u ? '-' . self::EN_1_19[$u] : '');
    }

    /** 0–999 in words */
    private static function under1000(int $n, string $lang): string
    {
        if ($n < 100) return self::under100($n, $lang);
        $h = intdiv($n, 100);
        $r = $n % 100;
        if ($lang === 'bn') {
            $s = self::BN_1_99[$h] . 'শো';
            return $r ? $s . ' ' . self::under100($r, 'bn') : $s;
        }
        $s = self::EN_1_19[$h] . ' hundred';
        return $r ? $s . ' and ' . self::under100($r, 'en') : $s;
    }

    /**
     * A whole number in words, on the Bangladeshi scale (thousand, lakh, crore).
     * 189000 → "এক লাখ ঊননব্বই হাজার" / "one lakh eighty-nine thousand"
     */
    public static function number(int $n, string $lang = 'en'): string
    {
        if ($n === 0) return $lang === 'bn' ? 'শূন্য' : 'zero';
        $neg = $n < 0;
        $n = abs($n);
        $parts = [];

        $units = $lang === 'bn'
            ? [10000000 => 'কোটি', 100000 => 'লাখ', 1000 => 'হাজার']
            : [10000000 => 'crore', 100000 => 'lakh', 1000 => 'thousand'];

        foreach ($units as $size => $word) {
            if ($n >= $size) {
                $q = intdiv($n, $size);
                $n %= $size;
                // a crore count can itself run past 99
                $parts[] = ($q < 1000 ? self::under1000($q, $lang) : self::number($q, $lang)) . ' ' . $word;
            }
        }
        if ($n > 0) $parts[] = self::under1000($n, $lang);

        $s = implode(' ', $parts);
        return $neg ? ($lang === 'bn' ? 'মাইনাস ' . $s : 'minus ' . $s) : $s;
    }

    /**
     * Money in words, rounded the way a person rounds it out loud.
     * Nobody says "one lakh eighty-nine thousand three hundred and twenty taka".
     */
    public static function money(float $v, string $lang = 'en'): string
    {
        $neg = $v < 0;
        $a = abs($v);
        $taka = $lang === 'bn' ? 'টাকা' : 'taka';
        $about = $lang === 'bn' ? 'প্রায় ' : 'about ';

        if ($a < 1) return ($lang === 'bn' ? 'শূন্য ' : 'zero ') . $taka;

        $approx = false;
        if ($a >= 10000000) {          // crore — keep two decimal places of a crore
            $r = round($a / 100000) * 100000;      // to the nearest lakh
        } elseif ($a >= 100000) {      // lakh — to the nearest thousand
            $r = round($a / 1000) * 1000;
        } elseif ($a >= 10000) {       // ten-thousands — to the nearest hundred
            $r = round($a / 100) * 100;
        } else {
            $r = round($a);
        }
        if (abs($r - $a) > 0.5) $approx = true;

        $words = self::number((int) $r, $lang);
        $s = $words . ' ' . $taka;
        if ($approx) $s = self::APPROX . $s;
        if ($neg) $s = ($lang === 'bn' ? 'মাইনাস ' : 'minus ') . $s;
        return $s;
    }

    /* ---------------- turning written money back into a number ---------------- */

    private static function toFloat(string $digits): float
    {
        return (float) str_replace(',', '', Nlu::asciiDigits($digits));
    }

    /* ---------------- the pipeline ---------------- */

    /**
     * Written answer → the words to speak.
     * Safe to call twice; safe on text that contains none of these shapes.
     */
    public static function spoken(string $text, string $lang = 'en'): string
    {
        try {
            return self::build($text, $lang);
        } catch (Throwable $e) {
            if (class_exists('Log')) Log::warn('speech build failed', ['error' => $e->getMessage()]);
            return $text;                      // saying it plainly beats saying nothing
        }
    }

    private static function build(string $text, string $lang): string
    {
        $t = $text;
        $bn = $lang === 'bn';

        // 1. markdown and code fences never survive to speech
        $t = preg_replace('/```[\s\S]*?```/u', ' ', $t) ?? $t;
        $t = preg_replace('/`([^`]*)`/u', '$1', $t) ?? $t;
        $t = preg_replace('/\*\*|__|\*|#+\s?/u', '', $t) ?? $t;
        $t = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1', $t) ?? $t;

        // 2. a spoken menu path, not an address
        //    "HR → Payslips → Statement"  →  "HR, then Payslips, then Statement"
        $arrow = $bn ? ' মেনুতে ' : ', then ';
        $t = preg_replace('/\s*(?:→|->|›|»)\s*/u', $arrow, $t) ?? $t;

        // 3. never read a URL or a route placeholder aloud. Take the words that
        //    introduced it too, or the sentence is left dangling on a preposition.
        $URL = '(?:https?://\S+|/[A-Za-z0-9_\-/{}]{3,})';
        //    "… — /super-admin/payslips."  →  "…."
        $t = preg_replace('~\s*[—–-]\s*' . $URL . '\s*([.।])?~u', '$1', $t) ?? $t;
        //    "… prints from /salary/view/{id}."  →  "… prints."
        $t = preg_replace('~\s+(?:from|at|under|in|on|via|to|is)\s+' . $URL . '~iu', '', $t) ?? $t;
        $t = preg_replace('~\s*' . $URL . '~u', '', $t) ?? $t;
        $t = preg_replace('/\{[a-z_]+\}/iu', '', $t) ?? $t;
        //    whatever is left must not end on a bare preposition
        $t = preg_replace('/\s+(from|at|under|in|on|via|to)\s*([.।,!?])/iu', '$2', $t) ?? $t;
        $t = preg_replace('/\s+(from|at|under|in|on|via|to)\s*$/iu', '', $t) ?? $t;

        // 4. account codes are read digit by digit
        $codeWord = $bn ? 'হিসাব' : 'account';
        $t = preg_replace_callback(
            '/(' . preg_quote($codeWord, '/') . '\s*)([০-৯0-9]{4})/u',
            function ($m) use ($lang) { return $m[1] . self::digits($m[2], $lang) ?? $t; },
            $t
        ) ?? $t;

        // 5. money — every written shape, both scripts
        $t = preg_replace_callback(
            '/(−|-)?৳\s?([০-৯0-9][০-৯0-9.,]*)\s*(কোটি|লাখ|হাজার|Cr|L|k)?/u',
            function ($m) use ($lang) {
                $v = self::toFloat($m[2]) ?? $t;
                $mult = ['কোটি' => 1e7, 'Cr' => 1e7, 'লাখ' => 1e5, 'L' => 1e5, 'হাজার' => 1e3, 'k' => 1e3];
                if (!empty($m[3]) && isset($mult[$m[3]])) $v *= $mult[$m[3]];
                if (!empty($m[1])) $v = -$v;
                return self::money($v, $lang);
            },
            $t
        ) ?? $t;

        // 6. percentages — a decimal point is never spoken
        $t = preg_replace_callback(
            '/([০-৯0-9][০-৯0-9.,]*)\s?%/u',
            function ($m) use ($lang) {
                $v = self::toFloat($m[1]) ?? $t;
                $r = round($v);
                $word = $lang === 'bn' ? ' শতাংশ' : ' percent';
                $s = self::number((int) $r, $lang) . $word;
                if (abs($r - $v) > 0.05) $s = self::APPROX . $s;
                return $s;
            },
            $t
        ) ?? $t;

        // 7. the current year goes unsaid, as anyone would leave it
        $year = date('Y');
        $t = str_replace([' ' . $year, ' ' . Phrase::bnDigits($year)], '', $t);

        // 8. any number still carrying a decimal point ("0.9 months")
        $t = preg_replace_callback(
            '/(?<![০-৯0-9])([০-৯0-9]+)[.]([০-৯0-9]+)(?![০-৯0-9])/u',
            function ($m) use ($lang) {
                $v = (float) (Nlu::asciiDigits($m[1]) . '.' . Nlu::asciiDigits($m[2])) ?? $t;
                $r = max(1, (int) round($v));      // "0.9 months" is spoken as "about one month"
                return self::APPROX . self::number($r, $lang);
            },
            $t
        ) ?? $t;

        // 9. every remaining number becomes words — first the grouped ones
        //    (12,34,567), then the plain ones. A trailing full stop or comma is
        //    punctuation, not part of the number, so it must not block the match.
        $t = preg_replace_callback(
            '/(?<![০-৯0-9])([০-৯0-9]{1,3}(?:,[০-৯0-9]{2,3})+)(?![০-৯0-9])/u',
            function ($m) use ($lang) {
                return self::number((int) str_replace(',', '', Nlu::asciiDigits($m[1])), $lang) ?? $t;
            },
            $t
        ) ?? $t;
        $t = preg_replace_callback(
            '/(?<![০-৯0-9.])([০-৯0-9]+)(?!\.?[০-৯0-9])/u',
            function ($m) use ($lang) {
                return self::number((int) Nlu::asciiDigits($m[1]), $lang) ?? $t;
            },
            $t
        ) ?? $t;

        // 10. "(Emi Agro)-এ" is one place with a case ending — not an aside
        $t = preg_replace('/\s*\(([^)]*)\)\s*-\s*(এ|তে|য়|এর|কে|র)(?![\x{0980}-\x{09FF}])/u', ' $1 $2', $t) ?? $t;

        //     brackets and dashes are pauses, not sounds
        $t = preg_replace('/\s*\(([^)]*)\)\s*/u', ', $1, ', $t) ?? $t;
        $t = preg_replace('/\s*[—–]\s*/u', ', ', $t) ?? $t;
        $t = str_replace(['|', '·', '•', '~', '৳'], ' ', $t);

        // 11. resolve the approximation markers — one "about" per phrase, never two
        $about = $bn ? 'প্রায় ' : 'about ';
        $mark = self::APPROX;
        $near = $bn ? '(?:প্রায়|মোটামুটি)' : '(?:about|roughly|around|approximately)';
        //     an "about" already in the sentence swallows the marker
        $t = preg_replace('/(' . $near . ')\s*' . $mark . '/u', '$1 ', $t) ?? $t;
        $t = preg_replace('/' . $mark . '\s*(' . $near . ')/u', '$1 ', $t) ?? $t;
        $t = preg_replace('/' . $mark . '(?:\s*' . $mark . ')+/u', $mark, $t) ?? $t;
        $t = str_replace($mark, $about, $t);
        $t = preg_replace('/\b(' . $near . ')(\s+\1)+\b/u', '$1', $t) ?? $t;

        // 12. Bangla counters hug the number: "সাত টা" is written, "সাতটা" is spoken
        if ($bn) $t = preg_replace('/(\S)\s+(টা|টি|জন|টার|টির|খানা)(?![\x{0980}-\x{09FF}])/u', '$1$2', $t) ?? $t;

        // 13. English agreement after the number became a word
        if (!$bn) {
            $units = 'month|day|week|year|account|item|task|lead|project|hour|minute|payslip|person|entry|posting|company|department';
            $t = preg_replace('/\bone (' . $units . ')s\b/iu', 'one $1', $t) ?? $t;
            $t = str_replace('one people', 'one person', $t);
        }

        // 14. tidy the punctuation the pauses left behind
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+([।.,;:!?])/u', '$1', $t);
        $t = preg_replace('/,\s*,+/u', ',', $t) ?? $t;
        $t = preg_replace('/([।.])\s*,/u', '$1', $t) ?? $t;
        $t = preg_replace('/,\s*([।.])/u', '$1', $t) ?? $t;
        //     ", -এ" is the case ending of the word before the bracket, not a new clause
        $t = preg_replace('/,\s*-\s*(এ|তে|য়|এর|কে|র)(?![\x{0980}-\x{09FF}])/u', ' $1', $t) ?? $t;

        return trim($t);
    }

    /** read a run of digits one at a time: 1011 → "এক শূন্য এক এক" */
    public static function digits(string $s, string $lang = 'en'): string
    {
        $out = [];
        foreach (preg_split('//u', Nlu::asciiDigits($s), -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if ($ch >= '0' && $ch <= '9') $out[] = self::under100((int) $ch, $lang);
        }
        return implode(' ', $out);
    }

    /**
     * Voice answers should be shorter than screen answers. Keeps whole
     * sentences, and never drops the recommendation at the end.
     */
    public static function shorten(string $t, int $cap = 420): string
    {
        if (mb_strlen($t) <= $cap) return $t;
        $parts = preg_split('/(?<=[।.!?])\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [$t];
        if (count($parts) <= 2) return $t;
        $first = array_shift($parts);
        $last = array_pop($parts);
        $out = $first;
        foreach ($parts as $p) {
            if (mb_strlen($out) + mb_strlen($p) + mb_strlen($last) + 2 > $cap) break;
            $out .= ' ' . $p;
        }
        return trim($out . ' ' . $last);
    }
}
