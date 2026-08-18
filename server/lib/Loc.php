<?php
declare(strict_types=1);

/* ============================================================
   Loc — Bangla for the sentences EON did not write.

   The decision layer, the approval queue and the evaluation model
   all produce English strings from fixed templates. When the boss
   asks in Bangla, leaving those in English makes the answer read
   like two people talking. This turns the templates back into
   Bangla, pattern by pattern, and leaves proper nouns alone —
   a bank is still called BRAC BANK in Bangla.

   Anything it does not recognise comes back untouched, so a new
   decision type degrades to English rather than to nonsense.
   ============================================================ */
final class Loc
{
    /** ordered: the first pattern that matches wins */
    private static function rules(): array
    {
        return [
            // ---- finance ----
            ['/^(৳[\d.,]+\s?(?:Cr|L|k)?) payable is past due \((\d+) items?\)$/u',
             fn($m) => $m[1] . ' দেনার তারিখ পেরিয়ে গেছে (' . $m[2] . ' টা)'],

            ['/^(.+) is low: (৳[\d.,]+)$/u',
             fn($m) => $m[1] . ' এ টাকা কমে গেছে: ' . $m[2]],

            ['/^Transfer a float into (.+) before the next scheduled payment\.?$/u',
             fn($m) => 'পরের নির্ধারিত পেমেন্টের আগে ' . $m[1] . ' এ কিছু টাকা পাঠিয়ে দিন।'],

            ['/^Release the late salaries first, then suppliers older than (\d+) days\.?$/u',
             fn($m) => 'আগে দেরি হওয়া বেতনগুলো ছাড়ুন, তারপর ' . $m[1] . ' দিনের বেশি পুরনো সরবরাহকারীদের।'],

            // ---- people ----
            ['/^(\d+) payslips? for (\d{4})-(\d{2}) are unpaid — (৳[\d.,]+\s?(?:Cr|L|k)?)$/u',
             fn($m) => Phrase::monthName($m[2] . '-' . $m[3], 'bn') . ' মাসের ' . $m[1] . ' টা বেতন এখনো দেওয়া হয়নি — ' . $m[4]],

            ['/^Release the payroll — late pay is the fastest way to lose good people\.?$/u',
             fn($m) => 'বেতনটা ছেড়ে দিন — দেরিতে বেতন দেওয়াই ভালো লোক হারানোর সবচেয়ে দ্রুত পথ।'],

            ['/^(\d+) employees? are late on (\d+)%\+ of days \(last (\d+) days\)$/u',
             fn($m) => $m[1] . ' জন কর্মী তাদের ' . $m[2] . '%+ দিনে দেরি করে (গত ' . $m[3] . ' দিন)'],

            ['/^Written warning to the top two; late deduction only bites at (\d+) min\/month\.?$/u',
             fn($m) => 'উপরের দুজনকে লিখিত সতর্কতা দিন; কর্তন তো মাসে ' . $m[1] . ' মিনিট পেরোলে তবেই ধরে।'],

            // ---- ops ----
            ['/^(\d+) projects?\(?s?\)? at risk — worst: (.+)$/u',
             fn($m) => $m[1] . ' টা প্রকল্প ঝুঁকিতে — সবচেয়ে খারাপ অবস্থা ' . $m[2] . ' এর'],

            ['/^Ask the manager for a recovery plan on (.+) by tomorrow: re-baseline or add capacity, and stop new scope\.?$/u',
             fn($m) => $m[1] . ' এর জন্য ম্যানেজারের কাছে কালকের মধ্যে একটা রিকভারি প্ল্যান চান: হয় নতুন করে সময় ঠিক করুন নয় লোক বাড়ান, আর নতুন কাজ যোগ করা বন্ধ।'],

            ['/^Compliance overdue: (.+) \(was due (\d{4}-\d{2}-\d{2}), (\d+)d ago\)$/u',
             fn($m) => 'সময় পেরিয়ে গেছে: ' . $m[1] . ' (শেষ তারিখ ছিল ' . Phrase::day($m[2], 'bn') . ', ' . $m[3] . ' দিন আগে)'],

            ['/^File and pay (.+) today, then log the challan number against the entry\.?$/u',
             fn($m) => 'আজই ' . $m[1] . ' জমা দিয়ে টাকা পরিশোধ করুন, তারপর চালান নম্বরটা এন্ট্রির সাথে লিখে রাখুন।'],

            // ---- crm ----
            ['/^(\d+) open leads? (?:have|has) gone cold$/u',
             fn($m) => $m[1] . ' টা খোলা লিড ঠান্ডা হয়ে গেছে'],

            ['/^Give the owners (\d+) hours to touch each one, then move the rest to lost\.?$/u',
             fn($m) => 'যাদের দায়িত্বে আছে তাদের ' . $m[1] . ' ঘণ্টা সময় দিন প্রতিটায় হাত দেওয়ার, তারপর বাকিগুলো lost করে দিন।'],

            // ---- approval queue ----
            ['/^Payroll run (\d{4})-(\d{2}) — (\d+) payslips?$/u',
             fn($m) => Phrase::monthName($m[1] . '-' . $m[2], 'bn') . ' মাসের পে-রোল — ' . $m[3] . ' টা স্লিপ'],
        ];
    }

    /** small vocabulary for the fragments that show up inside "why" lists */
    private static function fragments(): array
    {
        return [
            '/^(\d+) salary payments? (?:are|is) late$/u' => fn($m) => $m[1] . ' টা বেতন দিতে দেরি হয়েছে',
            '/^cash available (৳[\d.,]+\s?(?:Cr|L|k)?)$/u' => fn($m) => 'হাতে আছে ' . $m[1],
            '/^(\d+) items? overdue$/u' => fn($m) => $m[1] . ' টার তারিখ পেরিয়েছে',
            '/^oldest (\d+) days?$/u' => fn($m) => 'সবচেয়ে পুরনোটা ' . $m[1] . ' দিনের',
        ];
    }

    /**
     * Turn one English template string into Bangla.
     * Returns the input unchanged for 'en', or when nothing matches.
     */
    public static function bn(string $text, string $lang = 'bn'): string
    {
        $text = trim($text);
        if ($lang !== 'bn' || $text === '') return $text;

        foreach (array_merge(self::rules(), []) as [$re, $fn]) {
            if (preg_match($re, $text, $m)) {
                return Phrase::localise($fn($m), 'bn');
            }
        }
        foreach (self::fragments() as $re => $fn) {
            if (preg_match($re, $text, $m)) {
                return Phrase::localise($fn($m), 'bn');
            }
        }
        // nothing matched — at least make the money and digits read as Bangla
        return Phrase::localise($text, 'bn');
    }

    /** map a list of template strings */
    public static function bnAll(array $items, string $lang = 'bn'): array
    {
        return array_map(fn($x) => self::bn((string) $x, $lang), $items);
    }
}
