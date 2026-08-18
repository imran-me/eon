<?php
declare(strict_types=1);

/* ============================================================
   Nlu — EON's understanding layer for the offline brain.

   Turns a boss's sentence into  intent + language + slots,
   in three scripts at once:
     en  English            "how much cash do we have"
     bn  Bangla (বাংলা)     "হাতে ক্যাশ কত আছে"
     bl  Banglish (roman)   "hate cash koto ache"

   Scoring, not first-match: every intent carries weighted cues,
   the best total wins, and the runners-up come back as
   alternatives so the answerer can offer "did you mean".
   ============================================================ */
final class Nlu
{
    public const BN_RANGE = '\x{0980}-\x{09FF}';

    /* ---------- text normalisation ---------- */

    /** Bengali digits → ASCII, so "৫০০০" and "5000" both parse. */
    public static function asciiDigits(string $s): string
    {
        return str_replace(
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $s
        );
    }

    /** lowercase, de-punctuate, collapse space — keeps Bangla letters intact */
    public static function norm(string $s): string
    {
        $s = self::asciiDigits(trim($s));
        $s = mb_strtolower($s, 'UTF-8');
        // Bangla danda and common punctuation → space
        $s = preg_replace('/[।?!,;:"\'\x{2018}\x{2019}\x{201C}\x{201D}()\[\]{}<>\/\\\\|*_~`]+/u', ' ', $s);
        $s = preg_replace('/[.](?!\d)/u', ' ', $s);          // keep 1.9 but drop sentence dots
        $s = preg_replace('/\s+/u', ' ', $s);
        // spelling variants the boss will mix freely (কী/কি, য়-forms)
        $s = strtr((string) $s, ["\u{0995}\u{09C0}" => "\u{0995}\u{09BF}", "\u{09DF}" => "\u{09AF}\u{09BC}", "\u{09DC}" => "\u{09A1}\u{09BC}", "\u{09DD}" => "\u{09A2}\u{09BC}"]);
        return trim((string) $s);
    }

    /** share of Bengali letters in the string (0..1) */
    public static function banglaRatio(string $s): float
    {
        $letters = preg_replace('/[^\p{L}]+/u', '', $s);
        $n = mb_strlen($letters, 'UTF-8');
        if ($n === 0) return 0.0;
        $bn = preg_match_all('/[' . self::BN_RANGE . ']/u', $letters);
        return $bn / $n;
    }

    /** romanised-Bangla giveaways — words that simply are not English */
    private const BANGLISH_MARKERS = [
        'koto', 'koto', 'kotto', 'ache', 'achhe', 'ase', 'nai', 'nei', 'hobe', 'hoyeche', 'hoise',
        'ki', 'kii', 'kake', 'kar', 'karo', 'keno', 'kobe', 'kothay', 'kothae', 'kivabe', 'kemon',
        'amar', 'amader', 'tumi', 'apni', 'apnar', 'taka', 'tk', 'beton', 'bfeton', 'chuti',
        'khoroch', 'kharoch', 'lav', 'labh', 'lokshan', 'bikri', 'pawna', 'paona', 'dena',
        'deri', 'late hoyeche', 'onupostit', 'upostit', 'hajira', 'kaj', 'projukti',
        'dao', 'dekhao', 'bolo', 'janao', 'ekhon', 'aaj', 'aj', 'ajke', 'gotokal', 'kalke',
        'mash', 'maser', 'bochor', 'bosor', 'sob', 'shob', 'kono', 'kichu', 'jonno', 'theke',
        'kon', 'kobe', 'valo', 'bhalo', 'kore', 'korche', 'korlo', 'korbo', 'beshi', 'kom',
        'onujayi', 'baar', 'boro', 'choto', 'jhukite', 'mile', 'dekhbo', 'dekhao', 'age',
        'samne', 'porer', 'bibhag', 'niyomito', 'salam', 'assalamu', 'dhonnobad', 'ache',
        'lagbe', 'pabo', 'pari', 'jano', 'paro', 'abedon', 'hisab', 'khotian', 'dhap',
    ];

    /** 'bn' | 'en' — which language should EON answer in? */
    public static function detectLang(string $raw, ?string $hint = null): string
    {
        if ($hint) {
            $h = strtolower($hint);
            if (strpos($h, 'bn') === 0 || strpos($h, 'ben') === 0) return 'bn';
            if (strpos($h, 'en') === 0) {
                // an explicit English setting still yields to a question typed in Bangla script
                return self::banglaRatio($raw) > 0.3 ? 'bn' : 'en';
            }
        }
        if (self::banglaRatio($raw) > 0.25) return 'bn';
        $n = ' ' . self::norm($raw) . ' ';
        $hits = 0;
        foreach (self::BANGLISH_MARKERS as $m) {
            if (strpos($n, ' ' . $m . ' ') !== false) $hits++;
            if ($hits >= 2) return 'bn';
        }
        // a single very strong marker in a short sentence is enough
        $words = count(explode(' ', trim($n)));
        return ($hits >= 1 && $words <= 5) ? 'bn' : 'en';
    }

    /* ---------- the intent catalogue ----------
       Each entry: cues in three scripts. A cue is  'phrase' => weight.
       Multi-word cues are matched as substrings (word-bounded for
       latin), single words are matched whole so "ar" never eats "are".
       'block' terms veto the intent when present.                     */
    private static function catalogue(): array
    {
        static $C = null;
        if ($C !== null) return $C;

        $C = [

            /* ============ EXECUTIVE / DECISION ============ */
            'brief' => [
                'en' => ['brief' => 3, 'briefing' => 3, 'morning brief' => 4, 'update me' => 3, 'catch me up' => 3,
                         'how are things' => 3, 'where do we stand' => 3, 'overall status' => 3, 'summary' => 2,
                         'give me the rundown' => 3, 'daily report' => 3, 'todays report' => 3, "today s report" => 4, 'the brief' => 4],
                'bn' => ['ব্রিফ' => 4, 'আজকের ব্রিফ' => 5, 'সারসংক্ষেপ' => 3, 'সার্বিক অবস্থা' => 4, 'খবর কি' => 3,
                         'অবস্থা কি' => 3, 'আজকের অবস্থা' => 4, 'সংক্ষেপে বলো' => 3, 'আপডেট দাও' => 3, 'পরিস্থিতি' => 4, 'পরিস্থিতি কেমন' => 5],
                'bl' => ['brief dao' => 4, 'ajker brief' => 5, 'obostha ki' => 3, 'khobor ki' => 3, 'update dao' => 3],
            ],
            'focus' => [
                'en' => ['what should i focus' => 5, 'what should i do' => 4, 'priority' => 3, 'priorities' => 3,
                         'needs my attention' => 4, 'most important' => 3, 'what matters' => 3, 'first thing' => 2,
                         'what is urgent' => 4, 'top priority' => 4, 'deal with first' => 5, 'do first' => 4, 'holding up' => 4],
                'bn' => ['কি করা উচিত' => 5, 'করা উচিত' => 5, 'কোনটা আগে' => 4, 'অগ্রাধিকার' => 4, 'জরুরি কি' => 4,
                         'কোনদিকে নজর' => 4, 'কি করব' => 4, 'সবচেয়ে জরুরি' => 4, 'প্রথমে+ধরব' => 5, 'প্রথমে কোনটা' => 5, 'আগে দেখব' => 4],
                'bl' => ['ki kora uchit' => 5, 'ki korbo' => 4, 'kon ta age' => 4, 'age dekhbo' => 5, 'joruri ki' => 4],
            ],
            'approvals' => [
                'en' => ['approval' => 4, 'approvals' => 4, 'approve' => 3, 'waiting for me' => 4, 'waiting on me' => 4,
                         'pending sign' => 4, 'sign off' => 3, 'my signature' => 3, 'awaiting approval' => 4,
                         'need my ok' => 3, 'authorisation' => 3, 'authorization' => 3, 'am i holding up' => 5, 'approval queue' => 5],
                'bn' => ['অনুমোদন' => 4, 'অনুমোদনের অপেক্ষা' => 5, 'আমার সই' => 4, 'স্বাক্ষর' => 3,
                         'অপেক্ষমাণ অনুমোদন' => 5, 'পাশ করতে হবে' => 3, 'সম্মতি' => 2, 'সই লাগবে' => 7, 'সই+কোথায়' => 8],
                'bl' => ['onumodon' => 4, 'approval ache' => 4, 'amar soi' => 7, 'soi lagbe' => 8],
            ],
            'risks' => [
                'en' => ['what am i missing' => 5, 'what is wrong' => 4, 'risk' => 3, 'risks' => 3, 'problem' => 3,
                         'problems' => 3, 'trouble' => 3, 'red flag' => 4, 'worry' => 3, 'worried about' => 4,
                         'blind spot' => 4, 'anything bad' => 3, 'concerns' => 3, 'what should worry me' => 5, 'red flag' => 5, 'red flags' => 5],
                'bn' => ['কি মিস করছি' => 5, 'ঝুঁকি' => 4, 'সমস্যা' => 6, 'কি সমস্যা' => 6, 'বিপদ' => 4,
                         'কোথায় গণ্ডগোল' => 7, 'গণ্ডগোল' => 6, 'কি ভুল' => 4, 'চিন্তার বিষয়' => 5, 'দুর্বলতা' => 6],
                'bl' => ['ki miss korchi' => 5, 'somossa' => 3, 'jhuki' => 3, 'ki bhul' => 3],
            ],
            'anomalies' => [
                'en' => ['anomaly' => 5, 'anomalies' => 5, 'unusual' => 4, 'suspicious' => 4, 'irregular' => 4,
                         'duplicate' => 4, 'duplicates' => 4, 'strange' => 3, 'odd' => 3, 'fraud' => 4,
                         'something off' => 4, 'out of the ordinary' => 4, 'anything off' => 5, 'off in the books' => 5, 'look irregular' => 5],
                'bn' => ['অস্বাভাবিক' => 5, 'সন্দেহজনক' => 5, 'গরমিল' => 5, 'অনিয়ম' => 5, 'ডুপ্লিকেট' => 4,
                         'দুইবার' => 3, 'অসঙ্গতি' => 5, 'কিছু কি ভুল' => 3],
                'bl' => ['osbabhabik' => 4, 'gormil' => 4, 'onioms' => 3, 'duplicate ache' => 4],
            ],
            'health' => [
                'en' => ['health score' => 8, 'how healthy' => 7, 'business health' => 8, 'overall health' => 8,
                         'how is the business' => 9, 'business doing' => 9, 'company health' => 8, 'scorecard' => 6],
                'bn' => ['স্বাস্থ্য স্কোর' => 7, 'ব্যবসার অবস্থা' => 7, 'কেমন চলছে' => 6, 'সার্বিক স্কোর' => 7],
                'bl' => ['health score' => 5, 'kemon cholche' => 4, 'business kemon' => 4],
            ],
            'since' => [
                'en' => ['since yesterday' => 5, 'what changed' => 4, 'what is new' => 4, 'anything new' => 4,
                         'what happened' => 3, 'new since' => 4, 'changes today' => 4],
                'bn' => ['গতকাল থেকে' => 5, 'কি পরিবর্তন' => 4, 'নতুন কি' => 4, 'কি হয়েছে' => 3, 'নতুন কিছু' => 4],
                'bl' => ['gotokal theke' => 5, 'notun ki' => 4, 'ki hoyeche' => 3],
            ],
            'forecast' => [
                'en' => ['forecast' => 5, 'projection' => 4, 'project the' => 3, 'next quarter' => 4, 'next month' => 3,
                         'predict' => 4, 'prediction' => 4, 'outlook' => 4, 'trend' => 3, 'where are we heading' => 4,
                         'will we' => 2, 'expected' => 2, 'project the next' => 6, 'next three months' => 6, 'projection' => 5, 'where are we heading' => 10, 'heading' => 5],
                'bn' => ['পূর্বাভাস' => 5, 'ভবিষ্যৎ' => 4, 'আগামী মাস' => 4, 'আগামী প্রান্তিক' => 5,
                         'কি হবে' => 3, 'প্রবণতা' => 4, 'সামনে কি' => 3, 'ভবিষ্যতে' => 5, 'দাঁড়াবে' => 4],
                'bl' => ['forecast dao' => 5, 'agami mash' => 4, 'ki hobe' => 3, 'provonota' => 3],
            ],

            /* ============ CASH & BANK ============ */
            'cash' => [
                'en' => ['cash' => 4, 'cash position' => 5, 'cash in hand' => 5, 'liquidity' => 4, 'how much money' => 4,
                         'money do we have' => 4, 'funds' => 3, 'balance in hand' => 4, 'available money' => 4,
                         'how much do we have' => 4, 'how much is available' => 5, 'available money' => 4, 'total funds' => 5],
                'bn' => ['ক্যাশ' => 4, 'নগদ' => 4, 'ক্যাশ কত' => 5, 'হাতে কত' => 5, 'টাকা কত আছে' => 5,
                         'তহবিল' => 3, 'নগদ অর্থ' => 4, 'কত টাকা আছে' => 5, 'হাতে টাকা' => 5, 'হাতে+ব্যাংকে' => 6, 'নগদ অবস্থা' => 5],
                'bl' => ['cash koto' => 5, 'taka koto' => 4, 'hate koto' => 5, 'nogod' => 4, 'taka koto ache' => 5],
                'block' => ['flow'],
            ],
            'bank_accounts' => [
                'en' => ['bank account' => 5, 'bank accounts' => 5, 'which bank' => 4, 'bank wise' => 5,
                         'bank balance' => 6, 'bank balances' => 6, 'balance by bank' => 5, 'our banks' => 4, 'bank statement' => 4, 'list the bank' => 5],
                'bn' => ['ব্যাংক অ্যাকাউন্ট' => 5, 'কোন ব্যাংকে' => 5, 'ব্যাংক ব্যালেন্স' => 5, 'ব্যাংকে কত' => 5,
                         'ব্যাংক অনুযায়ী' => 5, 'ব্যাংক হিসাব' => 4, 'ব্যাংকে+কত' => 6],
                'bl' => ['bank e koto' => 5, 'kon bank' => 4, 'bank balance' => 5],
            ],
            'petty_cash' => [
                'en' => ['petty cash' => 9, 'float' => 6, 'custodian' => 7, 'office cash' => 9, 'cash box' => 7],
                'bn' => ['পেটি ক্যাশ' => 10, 'খুচরা নগদ' => 9, 'অফিস ক্যাশ' => 10, 'হাতখরচ' => 6, 'ফ্লোট' => 7],
                'bl' => ['petty cash' => 10, 'office cash' => 10, 'float e koto' => 8],
            ],
            'burn_runway' => [
                'en' => ['burn rate' => 5, 'runway' => 5, 'how long will cash last' => 5, 'months of cash' => 5,
                         'cash flow' => 4, 'cashflow' => 4, 'survive' => 3, 'run out of money' => 8, 'spend a month' => 8, 'per month' => 5, 'a month' => 4],
                'bn' => ['টাকা কতদিন' => 8, 'কতদিন চলবে' => 8, 'নগদ প্রবাহ' => 6, 'ব্যয়ের হার' => 9,
                         'টাকা শেষ' => 8, 'কত মাস চলবে' => 9, 'মাসে কত খরচ হয়' => 9, 'শেষ হয়ে যাবে' => 8],
                'bl' => ['koto din cholbe' => 8, 'taka koto din' => 8, 'cash flow' => 4, 'mase koto khoroch' => 9, 'koto mash cholbe' => 9],
            ],

            /* ============ RECEIVABLE / PAYABLE ============ */
            'receivables' => [
                'en' => ['receivable' => 5, 'receivables' => 5, 'who owes us' => 5, 'owes us money' => 5, 'debtor' => 4,
                         'debtors' => 4, 'collect from' => 4, 'to collect' => 4, 'money coming in' => 4,
                         'outstanding from customer' => 7, 'outstanding from customers' => 7, 'ar aging' => 5, 'due to us' => 6, 'have to collect' => 7, 'not paid us' => 7, 'to collect' => 5],
                'bn' => ['কে টাকা দেবে' => 5, 'টাকা+দেবে' => 5, 'টাকা+পাব' => 5, 'পাওনা' => 5, 'বকেয়া আদায়' => 5, 'গ্রাহক বকেয়া' => 5,
                         'আমাদের পাওনা' => 5, 'কার কাছে পাওনা' => 5, 'টাকা পাব' => 5, 'আদায়যোগ্য' => 5, 'পাওয়ার আছে' => 6, 'টাকা+পাওয়ার' => 6],
                'bl' => ['ke taka debe' => 5, 'pawna' => 5, 'paona koto' => 5, 'taka pabo' => 5,
                         'amader taka' => 5, 'taka debe' => 5, 'taka dibe' => 5, 'ke dibe taka' => 5],
            ],
            'payables' => [
                'en' => ['payable' => 5, 'payables' => 5, 'what do we owe' => 5, 'we owe' => 4, 'creditor' => 4,
                         'creditors' => 4, 'bills to pay' => 5, 'due to supplier' => 5, 'money going out' => 4,
                         'ap aging' => 5, 'to be paid' => 4, 'have to pay' => 7, 'bills are due' => 7, 'outstanding to supplier' => 7, 'outstanding to suppliers' => 7],
                'bn' => ['কাকে টাকা দিতে হবে' => 5, 'টাকা+দিতে' => 5, 'দেনা' => 5, 'পরিশোধ করতে হবে' => 5, 'আমাদের দেনা' => 5,
                         'কার কাছে দেনা' => 5, 'টাকা দিতে হবে' => 5, 'প্রদেয়' => 5, 'বিল বাকি' => 6, 'বিল+বাকি' => 6],
                'bl' => ['kake taka dite hobe' => 5, 'dena' => 5, 'dite hobe' => 4],
            ],
            'overdue_payments' => [
                'en' => ['overdue payment' => 5, 'past due' => 5, 'late payment' => 4, 'missed payment' => 4,
                         'overdue bill' => 6, 'due this week' => 6, 'due today' => 5, 'late payments' => 7, 'late payment' => 6],
                'bn' => ['মেয়াদোত্তীর্ণ' => 6, 'সময় পার' => 5, 'বকেয়া পেমেন্ট' => 6, 'এই সপ্তাহে দিতে' => 6, 'তারিখ পেরিয়েছে' => 7, 'কি কি বকেয়া' => 7, 'এই সপ্তাহে+দিতে' => 7],
                'bl' => ['overdue' => 4, 'somoy par' => 4],
                // "overdue tasks" is work, not money — let the tasks intent have it
                'block' => ['task', 'tasks', 'kaj', 'টাস্ক'],
            ],

            /* ============ REPORTS ============ */
            'trial_balance' => [
                'en' => ['trial balance' => 6, 'tb' => 2, 'does it balance' => 4, 'debits and credits' => 4],
                'bn' => ['ট্রায়াল ব্যালেন্স' => 6, 'রেওয়ামিল' => 6, 'ডেবিট ক্রেডিট' => 4],
                'bl' => ['trial balance' => 6, 'rewamil' => 5],
            ],
            'balance_sheet' => [
                'en' => ['balance sheet' => 6, 'net worth' => 5, 'assets and liabilities' => 5, 'total assets' => 4,
                         'equity' => 3, 'financial position' => 4],
                'bn' => ['ব্যালেন্স শীট' => 6, 'স্থিতিপত্র' => 6, 'সম্পদ ও দায়' => 5, 'মোট সম্পদ' => 4,
                         'নিট সম্পদ' => 5, 'আর্থিক অবস্থা' => 4],
                'bl' => ['balance sheet' => 6, 'shompod' => 3],
            ],
            'profit_loss' => [
                'en' => ['profit' => 4, 'p&l' => 6, 'pnl' => 5, 'income statement' => 6, 'margin' => 4, 'loss' => 3,
                         'profitable' => 4, 'bottom line' => 4, 'earnings' => 4, 'how much did we make' => 5,
                         'profit and loss' => 6, 'make money' => 6, 'made money' => 6],
                'bn' => ['লাভ' => 5, 'মুনাফা' => 5, 'লোকসান' => 5, 'লাভ কত' => 6, 'আয় ব্যয়' => 5,
                         'লাভ লোকসান' => 6, 'নিট মুনাফা' => 6, 'কত লাভ' => 6, 'মার্জিন' => 6],
                'bl' => ['lav koto' => 6, 'labh' => 5, 'munafa' => 5, 'lokshan' => 5, 'profit koto' => 5],
            ],
            'revenue' => [
                'en' => ['revenue' => 5, 'sales' => 4, 'turnover' => 5, 'top line' => 4, 'income this month' => 5,
                         'how much did we sell' => 5, 'billing' => 3],
                'bn' => ['বিক্রি' => 5, 'বিক্রি+কত' => 6, 'রাজস্ব' => 5, 'আয়' => 4, 'বিক্রয়' => 5, 'কত বিক্রি' => 6, 'টার্নওভার' => 5],
                'bl' => ['bikri' => 5, 'bikri koto' => 6, 'ay koto' => 5, 'revenue koto' => 5],
                'block' => ['profit', 'লাভ'],
            ],
            'expenses' => [
                'en' => ['expense' => 4, 'expenses' => 4, 'spending' => 4, 'how much spent' => 5, 'cost' => 3,
                         'costs' => 3, 'outgoing' => 3, 'spend' => 3, 'expenditure' => 5, 'gone out' => 5, 'has gone out' => 6],
                'bn' => ['খরচ' => 5, 'ব্যয়' => 5, 'খরচ কত' => 6, 'কত খরচ' => 6, 'ব্যয় কত' => 6, 'খরচ হয়েছে' => 12, 'খরচ হলো' => 11, 'মোট খরচ' => 9],
                'bl' => ['khoroch' => 5, 'khoroch koto' => 6, 'kharoch' => 5, 'beye' => 3],
            ],
            'expense_by_category' => [
                'en' => ['by category' => 5, 'biggest expense' => 5, 'where is the money going' => 12, 'money going' => 8,
                         'top expense' => 5, 'expense breakdown' => 5, 'category wise' => 5, 'which head' => 4,
                         'largest cost' => 5, 'what are we spending on' => 5],
                'bn' => ['কোন খাতে খরচ' => 6, 'খাত+খরচ' => 6, 'খরচ+বেশি' => 5, 'খরচ+কোথায়' => 5, 'সবচেয়ে বেশি খরচ' => 6, 'খরচের খাত' => 6, 'কিসে খরচ' => 5,
                         'খাত অনুযায়ী' => 5, 'টাকা কোথায় যাচ্ছে' => 12, 'কোথায় যাচ্ছে' => 11],
                'bl' => ['kon khate khoroch' => 8, 'sobcheye beshi khoroch' => 8, 'kise khoroch' => 6, 'khoroch kothay' => 12],
            ],
            'budget' => [
                'en' => ['budget' => 5, 'over budget' => 6, 'budget variance' => 6, 'within budget' => 5,
                         'budget vs actual' => 6, 'overspend' => 5, 'overspending' => 5],
                'bn' => ['বাজেট' => 5, 'বাজেট ছাড়িয়ে' => 6, 'বাজেটের বেশি' => 6, 'বাজেটের মধ্যে' => 5],
                'bl' => ['budget' => 5, 'budget er beshi' => 6],
            ],
            'account_ledger' => [
                'en' => ['ledger' => 5, 'account ledger' => 6, 'statement of account' => 5, 'account code' => 5,
                         'chart of accounts' => 5, 'account balance' => 5, 'gl' => 3, 'general ledger' => 6, 'account' => 4, 'balance on account' => 7, 'in account' => 5, 'read account' => 6],
                'bn' => ['খতিয়ান' => 6, 'লেজার' => 5, 'হিসাব বিবরণী' => 5, 'হিসাব কোড' => 5, 'সাধারণ খতিয়ান' => 6, 'হিসাবে কত' => 6, 'হিসাবের ব্যালেন্স' => 7, 'হিসাব' => 4],
                'bl' => ['ledger' => 5, 'khotian' => 6, 'hisab code' => 5, 'account+koto' => 6],
            ],
            'journal' => [
                'en' => ['journal' => 5, 'journal entry' => 6, 'journal entries' => 6, 'voucher' => 4, 'postings' => 4,
                         'entries today' => 4, 'double entry' => 4],
                'bn' => ['জার্নাল' => 5, 'দাখিলা' => 6, 'ভাউচার' => 5, 'জার্নাল এন্ট্রি' => 6],
                'bl' => ['journal' => 5, 'voucher' => 4, 'dakhila' => 5],
            ],
            'loans' => [
                'en' => ['loan' => 4, 'loans' => 4, 'emi' => 5, 'borrowing' => 5, 'installment' => 4,
                         'instalment' => 4, 'debt' => 4, 'bank loan' => 5, 'repayment' => 4],
                'bn' => ['ঋণ' => 5, 'লোন' => 5, 'কিস্তি' => 5, 'ধার' => 4, 'ব্যাংক ঋণ' => 6, 'ঋণ পরিশোধ' => 5],
                'bl' => ['loan' => 4, 'rin' => 4, 'kisti' => 5],
            ],
            'advances' => [
                'en' => ['advance salary' => 6, 'salary advance' => 6, 'advance' => 4, 'advances' => 4],
                'bn' => ['অগ্রিম' => 5, 'অগ্রিম বেতন' => 6, 'এডভান্স' => 5],
                'bl' => ['advance' => 4, 'ogrim' => 5, 'advance beton' => 6],
            ],
            'tax' => [
                'en' => ['tax' => 4, 'vat' => 5, 'tds' => 5, 'vds' => 5, 'mushak' => 6, 'withholding' => 5,
                         'income tax' => 5, 'statutory' => 4, 'nbr' => 5],
                'bn' => ['ভ্যাট' => 6, 'ট্যাক্স' => 6, 'উৎসে কর' => 7, 'আয়কর' => 6, 'মূসক' => 7, 'করের' => 5],
                'bl' => ['vat' => 6, 'tax' => 5],
            ],
            'company_compare' => [
                'en' => ['which company' => 5, 'compare companies' => 6, 'company wise' => 6, 'by company' => 5,
                         'best performing company' => 6, 'worst company' => 5, 'across companies' => 5, 'compare the companies' => 7, 'compare companies' => 7],
                'bn' => ['কোন কোম্পানি' => 5, 'কোম্পানি অনুযায়ী' => 6, 'কোম্পানি ভিত্তিক' => 6,
                         'সেরা কোম্পানি' => 6, 'কোন প্রতিষ্ঠান' => 5, 'কোম্পানিগুলোর তুলনা' => 8, 'তুলনা দাও' => 6],
                'bl' => ['kon company' => 5, 'company onujayi' => 5],
            ],

            /* ============ PAYROLL & PEOPLE ============ */
            'payroll' => [
                'en' => ['payroll' => 6, 'salary bill' => 6, 'wage bill' => 6, 'payslip' => 5, 'pay slip' => 5,
                         'salaries' => 5, 'salary' => 4, 'wages' => 4, 'monthly salary cost' => 6],
                'bn' => ['বেতন' => 6, 'পে-রোল' => 8, 'পেরোল' => 8, 'বেতন বিল' => 9, 'পে স্লিপ' => 7,
                         'পেস্লিপ' => 7, 'বেতন ভাতা' => 6, 'মজুরি' => 5, 'বেতন+খরচ' => 9, 'বেতন খরচ' => 9],
                'bl' => ['beton' => 5, 'payroll' => 6, 'payslip' => 5, 'beton bill' => 6],
            ],
            'payroll_unpaid' => [
                'en' => ['salary paid' => 5, 'unpaid salary' => 6, 'salary pending' => 6, 'salary due' => 5,
                         'has salary been paid' => 6, 'who has not been paid' => 6, 'pending payslip' => 6],
                'bn' => ['বেতন পরিশোধ হয়েছে' => 6, 'বেতন বাকি' => 6, 'বেতন দেওয়া হয়নি' => 6,
                         'বেতন কি দেওয়া হয়েছে' => 6, 'অপরিশোধিত বেতন' => 6],
                'bl' => ['beton porishodh' => 6, 'beton baki' => 6, 'beton deya hoyeche' => 6],
            ],
            'deduction_rules' => [
                'en' => ['how is late deduction' => 6, 'deduction calculated' => 6, 'deduction rule' => 6,
                         'how do you calculate' => 5, 'why was deducted' => 5, 'grace period' => 5,
                         'late rule' => 7, 'absent deduction' => 9, 'how is salary calculated' => 9, 'why was money deducted' => 9, 'was deducted' => 7, 'deduction' => 8, 'deduction rule' => 10, 'deduction work' => 10],
                'bn' => ['কর্তন কিভাবে' => 12, 'বিলম্ব কর্তন' => 12, 'কর্তনের নিয়ম' => 12, 'কিভাবে হিসাব' => 8,
                         'কেন কাটা হলো' => 10, 'কাটা হলো' => 9, 'বেতন কিভাবে হিসাব' => 12, 'কর্তন' => 9],
                'bl' => ['deduction kivabe' => 12, 'kata hoyeche keno' => 9, 'niyom ki' => 5, 'beton kivabe hisab' => 12, 'deduction' => 8],
            ],
            'overtime' => [
                'en' => ['overtime' => 6, 'ot hours' => 5, 'extra hours' => 5, 'worked late' => 4],
                'bn' => ['ওভারটাইম' => 6, 'অতিরিক্ত সময়' => 6, 'বাড়তি কাজ' => 5],
                'bl' => ['overtime' => 6, 'otirikto somoy' => 5],
            ],
            'headcount' => [
                'en' => ['how many employees' => 6, 'headcount' => 6, 'staff strength' => 6, 'total employees' => 6,
                         'how many people' => 5, 'team size' => 5, 'manpower' => 5, 'how many staff' => 6],
                'bn' => ['কতজন কর্মী' => 6, 'কতজন+কর্মী' => 6, 'কত+কর্মচারী' => 6, 'জনবল' => 6, 'কতজন কাজ করে' => 8, 'কতজন' => 5, 'কত কর্মচারী' => 6, 'মোট কর্মী' => 6, 'কতজন আছে' => 5,
                         'লোকবল' => 5],
                'bl' => ['kotojon kormi' => 6, 'koto employee' => 6, 'jonobol' => 5],
            ],
            'departments' => [
                'en' => ['department' => 5, 'departments' => 5, 'department wise' => 6, 'which department' => 5,
                         'designation' => 4, 'org structure' => 5],
                'bn' => ['বিভাগ' => 5, 'ডিপার্টমেন্ট' => 5, 'বিভাগ অনুযায়ী' => 6, 'কোন বিভাগ' => 5, 'পদবি' => 4],
                'bl' => ['department' => 5, 'bibhag' => 5, 'bibhag onujayi' => 7],
            ],
            'attendance_today' => [
                'en' => ['attendance' => 5, 'who is present' => 6, 'absent today' => 6, 'who is absent' => 6,
                         'present today' => 6, 'who is in' => 5, 'who came' => 4, 'headcount today' => 5,
                         'roll call' => 5, 'how many came' => 6, 'came today' => 5],
                'bn' => ['হাজিরা' => 6, 'আজ কে অনুপস্থিত' => 6, 'কে অনুপস্থিত' => 6, 'কে উপস্থিত' => 6,
                         'আজ কে এসেছে' => 6, 'উপস্থিতি' => 6, 'কে আসেনি' => 6, 'কে কে এসেছে' => 7, 'কতজন এসেছে' => 7, 'এসেছে' => 4],
                'bl' => ['aj ke onupostit' => 6, 'hajira' => 6, 'ke aseni' => 6, 'ke eseche' => 5],
            ],
            'late_today' => [
                'en' => ['who came late' => 6, 'late today' => 6, 'latecomers' => 6, 'who was late' => 6,
                         'late arrival' => 6, 'late arrivals' => 7],
                'bn' => ['কে দেরি করে এসেছে' => 8, 'আজ কে লেট' => 8, 'দেরিতে এসেছে' => 8, 'বিলম্বে এসেছে' => 8, 'কে লেট' => 7, 'লেট করেছে' => 7],
                'bl' => ['ke deri korlo' => 6, 'ke late' => 6, 'deri kore eseche' => 6],
            ],
            'chronic_late' => [
                'en' => ['always late' => 6, 'chronic late' => 6, 'habitual' => 5, 'punctuality' => 6,
                         'late pattern' => 7, 'late patterns' => 7, 'repeatedly late' => 6, 'worst attendance' => 5, 'chronically late' => 8],
                'bn' => ['প্রায়ই দেরি' => 8, 'নিয়মিত দেরি' => 8, 'সময়ানুবর্তিতা' => 8, 'বারবার লেট' => 8, 'নিয়মিত+দেরি' => 9, 'প্রায়ই+দেরি' => 9],
                'bl' => ['baar baar late' => 8, 'niyomito deri' => 8, 'niyomito+deri' => 9, 'baar baar' => 7],
            ],
            'leaves' => [
                'en' => ['leave' => 4, 'leaves' => 4, 'on leave' => 5, 'leave balance' => 6, 'leave request' => 6,
                         'holiday request' => 5, 'vacation' => 4, 'time off' => 5, 'pending leave' => 6],
                'bn' => ['ছুটি' => 5, 'ছুটির আবেদন' => 6, 'ছুটি বাকি' => 6, 'ছুটিতে আছে' => 6, 'কে ছুটিতে' => 6],
                'bl' => ['chuti' => 5, 'chutir abedon' => 6, 'ke chutite' => 6],
            ],
            'holidays' => [
                'en' => ['holiday' => 6, 'holidays' => 7, 'next holiday' => 9, 'public holiday' => 9,
                         'office closed' => 7, 'weekend' => 3, 'holiday calendar' => 9, 'holidays are coming' => 9],
                'bn' => ['সরকারি ছুটি' => 9, 'ছুটির দিন' => 9, 'পরবর্তী ছুটি' => 9, 'অফিস বন্ধ' => 7, 'পরের ছুটি' => 10, 'ছুটির ক্যালেন্ডার' => 10, 'সামনে+ছুটি' => 9, 'ছুটি কবে' => 9],
                'bl' => ['sorkari chuti' => 9, 'porer chuti' => 10, 'samne+chuti' => 9, 'chuti kobe' => 9],
            ],
            'employee_requests' => [
                'en' => ['employee request' => 7, 'employee requests' => 7, 'staff request' => 7, 'staff requests' => 7, 'loan request' => 6, 'request pending' => 6,
                         'application from' => 4, 'has applied' => 6, 'employee applications' => 7, 'requests pending' => 7],
                'bn' => ['কর্মচারীর আবেদন' => 7, 'কর্মীর আবেদন' => 7, 'কর্মীদের আবেদন' => 7, 'আবেদন অপেক্ষমাণ' => 6, 'ঋণের আবেদন' => 6, 'আবেদন' => 5, 'আবেদন করেছে' => 7],
                'bl' => ['kormir abedon' => 7, 'kormider abedon' => 7, 'abedon' => 5, 'request pending' => 6],
            ],
            'evaluate_person' => [
                'en' => ['how is performing' => 6, 'performing' => 6, 'evaluate' => 5, 'performance of' => 6, 'tell me about' => 5,
                         'profile of' => 5, 'how good is' => 5, 'assessment' => 5, 'rate the employee' => 6],
                'bn' => ['কেমন করছে' => 5, 'মূল্যায়ন' => 6, 'পারফরম্যান্স' => 5, 'সম্পর্কে বলো' => 4,
                         'কেমন কাজ করছে' => 6],
                'bl' => ['kemon korche' => 5, 'mullayon' => 5, 'performance kemon' => 5],
            ],
            'online_now' => [
                'en' => ['who is online' => 6, 'online now' => 6, 'who is working' => 5, 'currently active' => 5,
                         'last seen' => 5, 'online' => 5, 'anyone online' => 7],
                'bn' => ['কে অনলাইনে' => 6, 'এখন কে কাজ করছে' => 6, 'সক্রিয়' => 4, 'শেষ দেখা' => 5],
                'bl' => ['ke online' => 6, 'ekhon ke' => 5, 'kaj korche ekhon' => 7],
            ],

            /* ============ OPS / PROJECTS ============ */
            'tasks' => [
                'en' => ['task' => 4, 'tasks' => 4, 'overdue task' => 6, 'overdue tasks' => 7, 'workload' => 5, 'to do' => 3,
                         'assignments' => 4, 'pending work' => 6, 'work is pending' => 6, 'who is overloaded' => 6],
                'bn' => ['টাস্ক' => 5, 'কাজ' => 3, 'বকেয়া কাজ' => 6, 'কাজের চাপ' => 6, 'অসম্পূর্ণ কাজ' => 6],
                'bl' => ['task' => 4, 'kaj baki' => 5, 'kajer chap' => 6],
            ],
            'projects' => [
                'en' => ['project' => 5, 'projects' => 5, 'at risk' => 5, 'milestone' => 5, 'delivery' => 4,
                         'project progress' => 6, 'behind schedule' => 6],
                'bn' => ['প্রকল্প' => 6, 'প্রজেক্ট' => 6, 'প্রকল্পের অগ্রগতি' => 6, 'পিছিয়ে আছে' => 5],
                'bl' => ['project' => 5, 'prokolpo' => 6, 'project kemon' => 5],
            ],
            'todos' => [
                'en' => ['office todo' => 9, 'office todos' => 9, 'office to-dos' => 9, 'office to do' => 9, 'checklist' => 8, 'todo list' => 8],
                'bn' => ['অফিস কাজের তালিকা' => 10, 'অফিসের কাজের তালিকা' => 11, 'চেকলিস্ট' => 9, 'করণীয় তালিকা' => 9, 'কাজের তালিকা' => 9],
                'bl' => ['office todo' => 6, 'checklist' => 5],
            ],

            /* ============ CRM ============ */
            'pipeline' => [
                'en' => ['pipeline' => 6, 'lead' => 4, 'leads' => 5, 'prospect' => 5, 'crm' => 5, 'conversion' => 5,
                         'follow up' => 5, 'deal' => 4, 'deals' => 4, 'sales funnel' => 6, 'opportunity' => 4, 'follow ups' => 5, 'follow ups due' => 6],
                'bn' => ['পাইপলাইন' => 6, 'লিড' => 5, 'সম্ভাব্য গ্রাহক' => 6, 'ফলোআপ' => 5, 'রূপান্তর' => 5,
                         'বিক্রয় সুযোগ' => 5, 'কনভার্শন' => 7, 'সিআরএম' => 7],
                'bl' => ['pipeline' => 6, 'lead' => 4, 'follow up' => 5],
            ],
            'customers' => [
                'en' => ['customer' => 5, 'customers' => 5, 'client' => 4, 'clients' => 4, 'top customer' => 6,
                         'biggest client' => 6, 'buyer' => 4],
                'bn' => ['গ্রাহক' => 5, 'কাস্টমার' => 5, 'ক্রেতা' => 5, 'সেরা গ্রাহক' => 7, 'বড় গ্রাহক' => 7, 'কতজন+গ্রাহক' => 9],
                'bl' => ['customer' => 5, 'grahok' => 5, 'boro customer' => 6],
            ],
            'suppliers' => [
                'en' => ['supplier' => 5, 'suppliers' => 5, 'vendor' => 5, 'vendors' => 5, 'purchase from' => 4],
                'bn' => ['সরবরাহকারী' => 6, 'ভেন্ডর' => 5, 'বিক্রেতা' => 4, 'সাপ্লায়ার' => 5],
                'bl' => ['supplier' => 5, 'vendor' => 5],
            ],

            /* ============ META ============ */
            'navigation' => [
                'en' => ['where is' => 5, 'where do i find' => 6, 'where can i see' => 6, 'which menu' => 6,
                         'how do i get to' => 6, 'which page' => 5, 'where to find' => 6, 'navigate to' => 5,
                         'which screen' => 7, 'where in the erp' => 8, 'how do i open' => 6, 'where do i set' => 8, 'where are the' => 7, 'where do i see' => 8, 'where is the' => 7, 'where is' => 7, 'where are' => 6, 'which screen shows' => 8, 'how do i get to' => 9, 'get to the' => 7],
                'bn' => ['কোথায় পাবো' => 6, 'কোথায় পাব' => 6, 'কোথায়+পাব' => 6, 'কোথায়+দেখ' => 6, 'কোথায়+আছে' => 5, 'কোন মেনুতে' => 6, 'কিভাবে যাবো' => 6,
                         'কোথায় দেখব' => 8, 'কোন পেজে' => 7, 'কোথায় আছে' => 6, 'কোথায় বসাবো' => 8, 'কোথায়' => 4, 'কোন স্ক্রিনে' => 8],
                'bl' => ['kothay pabo' => 8, 'kon menu' => 8, 'kivabe jabo' => 8, 'kothay ache' => 6, 'kothay' => 4],
            ],
            'howto' => [
                'en' => ['how does it work' => 9, 'how does the' => 7, 'how does' => 7, 'does it work' => 8, 'work' => 3, 'explain' => 8, 'the process' => 8, 'what does it mean' => 6,
                         'what is the rule' => 8, 'why does' => 5, 'why must' => 7, 'how is it posted' => 8, 'get posted' => 8, 'workflow' => 6,
                         'what happens when' => 8, 'process for' => 6, 'the process' => 6, 'how is a' => 6, 'how is the' => 6, 'worked out' => 7,
                         'is missing' => 7, 'reports is the erp' => 8, 'how does an' => 7, 'how do i' => 5],
                'bn' => ['কিভাবে কাজ করে' => 8, 'বুঝিয়ে বলো' => 8, 'মানে কি' => 6, 'নিয়ম কি' => 6,
                         'কি হয় যখন' => 7, 'প্রক্রিয়া' => 7, 'কিভাবে বসে' => 8, 'কিভাবে হয়' => 7, 'কিভাবে সংশোধন' => 8,
                         'হলে কি হয়' => 8, 'ধাপগুলো' => 8, 'ধাপ' => 6, 'কিভাবে' => 5],
                'bl' => ['kivabe kaj kore' => 8, 'bujhiye bolo' => 8, 'mane ki' => 6, 'niyom' => 5, 'kivabe' => 6, 'kivabe bose' => 8, 'er dhap' => 8, 'dhap ki' => 8],
            ],
            'capabilities' => [
                'en' => ['what can you do' => 6, 'help me' => 4, 'what do you know' => 6, 'your abilities' => 6,
                         'what can i ask' => 6, 'options' => 3, 'commands' => 4],
                'bn' => ['তুমি কি পারো' => 6, 'কি কি পারো' => 6, 'সাহায্য' => 4, 'কি জিজ্ঞেস করতে পারি' => 6, 'তুমি কি জানো' => 7, 'কি জানো' => 6],
                'bl' => ['tumi ki paro' => 6, 'ki ki paro' => 6, 'help' => 3, 'jiggesh korte pari' => 7, 'ki jano' => 6],
            ],
            'greeting' => [
                'en' => ['hello' => 5, 'hi' => 4, 'hey' => 4, 'good morning' => 5, 'good afternoon' => 5,
                         'good evening' => 5, 'thanks' => 5, 'thank you' => 5, 'well done' => 4, 'salam' => 5],
                'bn' => ['হ্যালো' => 5, 'সালাম' => 5, 'আসসালামু' => 6, 'শুভ সকাল' => 6, 'ধন্যবাদ' => 6,
                         'কেমন আছো' => 5, 'শুভ অপরাহ্ন' => 5],
                'bl' => ['salam' => 5, 'assalamu' => 6, 'dhonnobad' => 6, 'kemon acho' => 5],
            ],
        ];
        return $C;
    }

    public static function intents(): array
    {
        return array_keys(self::catalogue());
    }

    /* ---------- matching ---------- */

    /** phrasings that ask for a place — a screen, a menu, an address */
    private const LOCATIVE = [
        'where is', 'where are', 'where do i', 'where can i', 'where to find', 'where in the erp',
        'which menu', 'which screen', 'which page', 'how do i get to', 'how do i open', 'how do i find',
        'কোথায়', 'কোন মেনু', 'কোন স্ক্রিন', 'কোন পেজ', 'kothay', 'kon menu', 'kon screen',
    ];

    /** idioms that borrow a locative shape but ask about data, not about a screen */
    private const NOT_LOCATIVE = [
        'where is the money going', 'money going', 'where are we heading', 'where do we stand',
        'টাকা কোথায় যাচ্ছে', 'কোথায় যাচ্ছে', 'khoroch kothay', 'taka kothay jacche',
        // "where do I have to sign" and "where is the problem" are not screen questions
        'সই লাগবে', 'সই কোথায়', 'soi lagbe', 'amar soi',
        'কোথায় সমস্যা', 'কোথায় গণ্ডগোল', 'দুর্বলতা কোথায়', 'সমস্যা কোথায়', 'গণ্ডগোল কোথায়',
    ];

    /** does this cue appear in the normalised haystack? word-bounded for latin cues */
    /** Bangla inflections that may follow a stem and still count as the same word */
    private const BN_SUFFIX = '\x{09C7}\x{09B0}|\x{09C7}|\x{09B0}|\x{099F}\x{09BE}|\x{0995}\x{09C7}|\x{09AF}\x{09BC}|\x{09A6}\x{09C7}\x{09B0}|\x{0997}\x{09C1}\x{09B2}\x{09CB}';

    private static function hit(string $hay, string $cue): bool
    {
        // "a+b" — both tokens must appear, in any order (Bangla word order is free)
        if (strpos($cue, '+') !== false) {
            foreach (explode('+', $cue) as $part) {
                $part = trim($part);
                if ($part !== '' && !self::hit($hay, $part)) return false;
            }
            return true;
        }
        if (preg_match('/^[a-z0-9 &\'-]+$/', $cue)) {
            return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($cue, '/') . '(?![a-z0-9])/u', $hay);
        }
        // Bangla: a short stem must not bleed into a longer word (কর "tax" vs করা "to do")
        if (mb_strlen($cue, 'UTF-8') <= 4 && preg_match('/[' . self::BN_RANGE . ']/u', $cue)) {
            $q = preg_quote($cue, '/');
            return (bool) preg_match('/(?<![' . self::BN_RANGE . '])' . $q . '(?:' . self::BN_SUFFIX . ')?(?![' . self::BN_RANGE . '])/u', $hay);
        }
        return mb_strpos($hay, $cue, 0, 'UTF-8') !== false;
    }

    /**
     * Parse a question.
     * @return array{intent:?string,score:float,lang:string,slots:array,alternatives:array,normalised:string}
     */
    public static function parse(string $raw, ?string $langHint = null): array
    {
        $lang = self::detectLang($raw, $langHint);
        $n = ' ' . self::norm($raw) . ' ';
        $scores = [];

        foreach (self::catalogue() as $intent => $def) {
            $score = 0.0;
            $blocked = false;
            foreach ((array) ($def['block'] ?? []) as $b) {
                if (self::hit($n, $b)) { $blocked = true; break; }
            }
            if ($blocked) continue;
            foreach (['en', 'bn', 'bl'] as $script) {
                foreach ((array) ($def[$script] ?? []) as $cue => $w) {
                    if (self::hit($n, $cue)) {
                        // longer cues are more specific — reward them
                        $score += $w * (1 + 0.12 * substr_count(trim($cue), ' '));
                    }
                }
            }
            if ($score > 0) $scores[$intent] = $score;
        }

        arsort($scores);
        $best = null;
        $bestScore = 0.0;
        foreach ($scores as $i => $s) { $best = $i; $bestScore = $s; break; }

        // "where do I find the payslip screen" is a NAVIGATION question about payroll,
        // not a payroll question. The frame wins; the data intent becomes the topic.
        $topic = null;

        $idiom = false;
        foreach (self::NOT_LOCATIVE as $x) { if (self::hit($n, $x)) { $idiom = true; break; } }
        $locative = false;
        if (!$idiom) {
            foreach (self::LOCATIVE as $x) { if (self::hit($n, $x)) { $locative = true; break; } }
        }

        foreach (['navigation', 'howto'] as $frame) {
            $fs = $scores[$frame] ?? 0;
            // a real "where do I find it" question is about the screen no matter how
            // loudly the subject scores; otherwise the frame has to earn it on points
            $wins = ($frame === 'navigation' && $locative && $fs > 0)
                || ($fs >= 5.0 && $fs >= $bestScore * 0.7);
            if ($wins && $best !== $frame) {
                foreach ($scores as $i => $sc) {
                    if ($i !== 'navigation' && $i !== 'howto') { $topic = $i; break; }
                }
                $best = $frame;
                $bestScore = $scores[$frame];
                break;
            }
        }
        if ($best === 'navigation' || $best === 'howto') {
            if ($topic === null) {
                foreach ($scores as $i => $sc) {
                    if ($i !== 'navigation' && $i !== 'howto') { $topic = $i; break; }
                }
            }
        }

        $alts = [];
        $c = 0;
        foreach ($scores as $i => $s) {
            if ($i === $best) continue;
            if ($s >= $bestScore * 0.55) { $alts[] = $i; }
            if (++$c >= 3) break;
        }

        return [
            'intent'      => ($bestScore >= 3.0) ? $best : null,
            'score'       => round($bestScore, 2),
            'lang'        => $lang,
            'slots'       => ['topic' => $topic] + self::slots($raw, $n, $lang),
            'alternatives' => $alts,
            'normalised'  => trim($n),
        ];
    }

    /* ---------- slot extraction ---------- */

    private const MONTHS_EN = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
        'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9,
        'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    private const MONTHS_BN = ['জানুয়ারি' => 1, 'ফেব্রুয়ারি' => 2, 'মার্চ' => 3, 'এপ্রিল' => 4, 'মে' => 5,
        'জুন' => 6, 'জুলাই' => 7, 'আগস্ট' => 8, 'সেপ্টেম্বর' => 9, 'অক্টোবর' => 10, 'নভেম্বর' => 11,
        'ডিসেম্বর' => 12];

    /** everything the answerer might need out of the sentence */
    public static function slots(string $raw, string $n, string $lang): array
    {
        $s = ['period' => null, 'month' => null, 'year' => null, 'account_code' => null,
              'top' => null, 'company_hint' => null, 'name_hint' => null, 'compare' => false];

        // ---- relative period ----
        if (preg_match('/\b(last month|previous month)\b/u', $n) || mb_strpos($n, 'গত মাস') !== false || mb_strpos($n, 'গতমাস') !== false || strpos($n, 'গত maser') !== false || preg_match('/\bgoto mash|got mash|last mash\b/u', $n)) {
            $s['period'] = 'last_month';
        } elseif (preg_match('/\b(this month|current month|mtd|month to date)\b/u', $n) || mb_strpos($n, 'এই মাস') !== false || mb_strpos($n, 'চলতি মাস') !== false || preg_match('/\bei mash|ei maser\b/u', $n)) {
            $s['period'] = 'this_month';
        } elseif (preg_match('/\b(this year|ytd|year to date)\b/u', $n) || mb_strpos($n, 'এই বছর') !== false || mb_strpos($n, 'চলতি বছর') !== false) {
            $s['period'] = 'this_year';
        } elseif (preg_match('/\b(last year|previous year)\b/u', $n) || mb_strpos($n, 'গত বছর') !== false) {
            $s['period'] = 'last_year';
        } elseif (preg_match('/\b(today|so far today)\b/u', $n) || mb_strpos($n, 'আজ') !== false || preg_match('/\bajke|aj\b/u', $n)) {
            $s['period'] = 'today';
        } elseif (preg_match('/\b(yesterday)\b/u', $n) || mb_strpos($n, 'গতকাল') !== false || preg_match('/\bgotokal|kalke\b/u', $n)) {
            $s['period'] = 'yesterday';
        } elseif (preg_match('/\b(this week|weekly)\b/u', $n) || mb_strpos($n, 'এই সপ্তাহ') !== false) {
            $s['period'] = 'this_week';
        } elseif (preg_match('/\b(quarter|quarterly)\b/u', $n) || mb_strpos($n, 'প্রান্তিক') !== false) {
            $s['period'] = 'quarter';
        }

        // ---- explicit month / year ----
        foreach (self::MONTHS_EN as $name => $num) {
            if (preg_match('/(?<![a-z])' . $name . '(?![a-z])/u', $n)) { $s['month'] = $num; break; }
        }
        if ($s['month'] === null) {
            foreach (self::MONTHS_BN as $name => $num) {
                if (mb_strpos($n, $name) !== false) { $s['month'] = $num; break; }
            }
        }
        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/u', $n, $m)) $s['year'] = (int) $m[1];
        if (preg_match('/(?<!\d)(20\d{2})[-\/](\d{1,2})(?!\d)/u', $n, $m)) { $s['year'] = (int) $m[1]; $s['month'] = (int) $m[2]; }

        // ---- account code: a bare 4-digit number that is not a year ----
        if (preg_match_all('/(?<!\d)(\d{4})(?!\d)/u', $n, $m)) {
            foreach ($m[1] as $cand) {
                if ((int) $cand >= 2000 && (int) $cand <= 2100) continue;   // that's a year
                $s['account_code'] = $cand;
                break;
            }
        }

        // ---- top N ----
        if (preg_match('/\btop\s+(\d{1,2})\b/u', $n, $m)) $s['top'] = (int) $m[1];
        elseif (preg_match('/\b(first|top)\b/u', $n)) $s['top'] = 5;
        elseif (mb_strpos($n, 'সেরা') !== false || mb_strpos($n, 'শীর্ষ') !== false) $s['top'] = 5;

        // ---- comparison intent ----
        if (preg_match('/\b(compare|versus|vs|against|than last|change from)\b/u', $n)
            || mb_strpos($n, 'তুলনা') !== false || mb_strpos($n, 'চেয়ে') !== false) {
            $s['compare'] = true;
        }

        // ---- company hint ----
        $cos = ['travels' => 'travels', 'travel' => 'travels', 'ট্রাভেল' => 'travels',
                'it solution' => 'it', 'it solutions' => 'it', 'আইটি' => 'it',
                'construction' => 'constructions', 'কনস্ট্রাকশন' => 'constructions',
                'wood art' => 'wood art', 'উড আর্ট' => 'wood art',
                'online shop' => 'online shop', 'shop' => 'online shop', 'শপ' => 'online shop',
                'properties' => 'properties', 'প্রপার্টি' => 'properties',
                'manufacturing' => 'manufacturing', 'ম্যানুফ্যাকচারিং' => 'manufacturing',
                'group' => 'group', 'গ্রুপ' => 'group'];
        foreach ($cos as $cue => $val) {
            if (self::hit($n, $cue)) { $s['company_hint'] = $val; break; }
        }

        // ---- person name hint: capitalised words in the raw text, or text after "of/about/এর" ----
        $s['name_hint'] = self::nameHint($raw, $n, $lang);

        return $s;
    }

    /** best guess at a person's name inside the question */
    private static function nameHint(string $raw, string $n, string $lang): ?string
    {
        // "salary of Rakib", "tell me about Rakib", "Rakib er beton"
        if (preg_match('/\b(?:of|about|for)\s+([A-Z][a-zA-Z.]+(?:\s+[A-Z][a-zA-Z.]+){0,3})/u', $raw, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})/u', $raw, $m)) {
            $cand = trim($m[1]);
            // ignore obvious non-names
            if (!preg_match('/^(How|What|Who|Where|When|Why|Show|Give|Tell|The|Is|Are|Do|Does|Can|Ask|EON|Eon)\b/u', $cand)) {
                return $cand;
            }
        }
        // Bangla possessive:  "<name> এর বেতন"  /  "<name> er beton"
        if (preg_match('/([\x{0980}-\x{09FF}]{3,}(?:\s+[\x{0980}-\x{09FF}]{2,}){0,2})\s*(?:এর|র)\s/u', $raw, $m)) {
            $cand = trim($m[1]);
            // the possessive suffix may have been split off, so compare by prefix
            $stop = ['আজকের', 'আমাদের', 'আমার', 'কোম্পানির', 'প্রতিষ্ঠানের', 'অফিসের', 'কর্মীর',
                     'কর্মচারীর', 'সবার', 'এই', 'গত', 'এবারের', 'মাসের', 'বছরের', 'তার', 'তাদের'];
            $isStop = false;
            foreach ($stop as $w) { if (mb_strpos($w, $cand, 0, 'UTF-8') === 0) { $isStop = true; break; } }
            if (!$isStop) return $cand;
        }
        return null;
    }
}
