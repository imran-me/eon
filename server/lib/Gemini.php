<?php
/* ============================================================
   EON · Gemini — understanding, not answering.

   The offline brain answers exactly but only what its rules were written
   for. Gemini reads any wording — বাংলা, Banglish, English, half a
   sentence — and turns it into something EON already does exactly: a
   question the offline brain answers from the ERP, a screen to open, or a
   question back when the boss was not clear. It never produces a figure:
   every number still comes from the ERP through Ops/Answer.

   Key: server/storage/gemini.key (private, kept across deploys), or
   config gemini.key, or env GEMINI_API_KEY. Never in the repo.
   ============================================================ */
declare(strict_types=1);

final class Gemini
{
    /** fast first; the next one when the first is overloaded (2.5 is closed to new keys) */
    private const MODELS = ['gemini-3.1-flash-lite', 'gemini-3.8-flash'];

    /** the conversation so far, oldest first ("boss: …" / "EON: …") — set by api/ask.php */
    public static array $recent = [];

    public static function key(): ?string
    {
        $f = dirname(__DIR__) . '/storage/gemini.key';
        if (is_file($f) && ($k = trim((string) file_get_contents($f))) !== '') return $k;
        if (class_exists('Config') && ($k = (string) (Config::get('gemini.key') ?? '')) !== '') return $k;
        return getenv('GEMINI_API_KEY') ?: null;
    }

    public static function enabled(): bool { return self::key() !== null; }

    /** one JSON-mode call; null on any failure, so the offline brain carries on */
    public static function json(string $system, string $user, float $timeout = 6.0): ?array
    {
        $key = self::key(); if (!$key) return null;
        $body = json_encode([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1],
        ], JSON_UNESCAPED_UNICODE);
        // a slow or absent API must cost seconds, not the answer: one overall budget, then the offline brain
        $res = false; $code = 0; $until = microtime(true) + 9.0;
        foreach (self::MODELS as $model) {
            if (microtime(true) > $until - 2) break;
            $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => (int) max(2, min(ceil($timeout), floor($until - microtime(true)))), CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key]]);
            $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($res !== false && $code === 200) break;
        }
        if ($res === false || $code !== 200) { if (class_exists('Log')) Log::warn('gemini failed', ['code' => $code]); return null; }
        $j = json_decode((string) $res, true);
        $t = $j['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $o = is_string($t) ? json_decode($t, true) : null;
        return is_array($o) ? $o : null;
    }

    /**
     * Answer from the fact sheet (Facts::sheet) — reasoning allowed, invention not: every figure
     * in the answer must be on the sheet, and a question the sheet cannot answer says so.
     */
    public static function analyze(string $q, string $lang, array $facts): ?string
    {
        $system = 'You are EON, the business assistant of Epal Group (Bangladesh). Answer the boss using ONLY the numbers in the FACT SHEET below — '
            . 'you may add, compare, rank, compute percentages and explain, but never invent or estimate a figure that is not derivable from the sheet. '
            . 'If the sheet does not hold what is needed, say plainly what is missing. Never name an ERP screen or report you are not given. '
            . 'Money is in Bangladeshi taka (৳); say large amounts in lakh/crore. Be brief: 2–4 sentences, the answer first, then one useful next step. '
            . ($lang === 'bn' ? 'Reply in natural office Bangla (Bangla script), keeping common English business words (profit, due, ledger, invoice, agent) as people in Dhaka say them.' : 'Reply in English.')
            . ' Reply as JSON: {"answer":"..."}' . "\n\nFACT SHEET:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE);
        $hist = self::$recent ? "Conversation so far:\n" . implode("\n", self::$recent) . "\n\nNow the boss asks: " : '';
        $o = self::json($system, $hist . $q, 8.0);
        $a = is_array($o) ? trim((string) ($o['answer'] ?? '')) : '';
        return $a !== '' ? $a : null;
    }

    /* ---------- the planner ---------- */

    private static ?string $catalog = null;
    private static function catalog(): string
    {
        if (self::$catalog !== null) return self::$catalog;
        $f = dirname(__DIR__) . '/kb/eon-intents.json';
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
        $lines = [];
        foreach (($j['intents'] ?? []) as $name => $ex) $lines[] = '- ' . $name . ': ' . implode(' | ', array_slice($ex, 0, 3));
        return self::$catalog = implode("\n", $lines);
    }

    /**
     * What does the boss want? Returns one of
     *   ['route' => 'ask', 'question' => '<English question EON answers exactly>']
     *   ['route' => 'navigate', 'screen' => '<ERP screen in English>']
     *   ['route' => 'clarify', 'question' => '<a short question back, in the boss's language>']
     *   ['route' => 'none']
     */
    public static function plan(string $q, string $lang): ?array
    {
        $system = <<<TXT
You are the understanding layer of EON, the assistant inside Epal Group's ERP (a Bangladeshi travel group: ticketing, contract flights, visa processing, contract files (manpower), plus accounts, payroll, HR, CRM, tasks). The boss speaks Bangla, Banglish (Bangla in Latin letters) or English, often loosely, by voice (speech-recognition errors happen; English words may arrive written in Bangla letters).

Your only job: map what the boss said to ONE thing EON can do exactly. Never answer the question yourself and never invent a number.

EON answers these questions exactly (intent: examples):
{CATALOG}
Also exactly: a named bank/wallet's balance ("how much is in bKash"), this month's salary still to pay, one person's salary paid or not, how many contract flights are still to fly, contract-flight money due, a person's details by name, a party's ledger or statement, tickets by passenger name, ticket counts by status.

Reply with JSON only, one of:
{"route":"ask","question":"<a short English question in the style of the examples above, keeping every name, number, month and filter the boss gave>"}
{"route":"navigate","screen":"<the ERP screen to open, in English, e.g. 'contract flight board', 'ticket orders', 'bank transfers', 'vendor ledger'>"}
{"route":"do","command":"<an English command for EON's action layer, naming the exact record: e.g. 'print the ledger of BSP IATA', 'open the statement of SALMAN TOURS & TRAVELS for October', 'export the agent ledger of MR RABBI to excel', 'reissue the ticket of MD SIFATUL ISLAM EMON', 'open the ticket of MD SIFATUL ISLAM EMON', 'refund the ticket PNR VPLX4H', 'offload passenger MR AMIT BAIDYA', 'move contract file 12 to visa stage', 'assign Imran a task: check ledger', 'pay Imran October salary'>"}
{"route":"analyze","question":"<the question in English>"}  (a breakdown by agent/vendor/client/section/company/category, a comparison, ranking, trend, "why", or anything the examples above do not cover exactly)
{"route":"clarify","question":"<one short question back to the boss, in {LANG}>"}
{"route":"none"}  (not about the business at all)

For navigate, keep the boss's own words for the screen, translated to English if needed ('flight dashboard' stays 'flight dashboard', 'টিকেট সেলস' becomes 'ticket sales').
Use "do" for any action on a record or report: open/print/download/export/share a ledger or statement, open/reissue/refund/void/reschedule a ticket, offload/deport, move a file or visa stage, assign a task, record a payment, an expense or an entry. Resolve "this/that/ওটা/এইটা/এই লেজার/তার" from the conversation so far and put the real name in the command.
Use "ask" ONLY when the question matches one of the examples above in meaning AND in the breakdown asked ("profit by agent" is NOT "profit" — that is analyze).
Prefer "ask" when the boss wants information, "navigate" when they want to go/open/see a screen with no specific figure, "clarify" only when you truly cannot tell which of two things is meant.
TXT;
        $system = str_replace(['{CATALOG}', '{LANG}'], [self::catalog(), $lang === 'bn' ? 'Bangla' : 'English'], $system);
        $hist = self::$recent ? "Conversation so far:\n" . implode("\n", self::$recent) . "\n\nNow the boss says: " : '';
        $o = self::json($system, $hist . $q);
        if (!$o || !isset($o['route'])) return null;
        return $o;
    }
}
