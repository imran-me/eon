<?php
/* ============================================================
   EON · schema-run — does EON know what the ERP's data is?

   Each case is a question and what the answer must contain (a table, a
   column, a value) — or null: a business question that must stay with
   the figure answerers, however much it sounds like the data's shape.
   Runs through Brain's doors in the same order (strong, then column).

     php tools/schema-run.php [--fail]
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/../server/lib/SchemaSense.php';
$fail = in_array('--fail', $argv, true);

$cases = [
    // where a thing lives — including things that are values, not tables
    ['where are ticket refunds stored', ['ticket_changes', 'refund']],
    ['where are voids kept', ['ticket_changes', 'void']],
    ['রিফান্ড কোন টেবিলে থাকে', ['ticket_changes', 'refund']],
    ['which table holds visa processes', ['visa_processes']],
    ['where is attendance stored', ['attendance']],
    ['বেতন কোন টেবিলে রাখা হয়', ['salar']],
    // vocabularies
    ['what statuses can a visa process have', ['visa_processes.status', 'approved']],
    ['ভিসার স্ট্যাটাস কী কী', ['visa_processes.status']],
    ['what kinds of ticket changes are there', ['ticket_changes.kind', 'reissue']],
    ['what stages can a contract file have', ['contract_files.status', 'file_received']],
    ['what payment methods exist in bank transfers', ['payment_method']],
    // a column, by name
    ['what is rbd in ticket purchases', ['ticket_purchases.rbd']],
    ['what does the bearer column mean in ticket changes', ['ticket_changes.bearer', 'customer']],
    ['ticket_purchases.refund_amount', ['ticket_purchases.refund_amount']],
    // shape
    ['describe the contract files table', ['contract_files', 'rows']],
    ['কন্ট্রাক্ট ফাইল টেবিলে কী আছে', ['contract_files', 'রেকর্ড']],
    ['how many rows in ticket changes', ['ticket_changes', 'rows']],
    ['what links to passport holders', ['passport_holders', 'ticket_purchases']],
    ['পাসপোর্ট হোল্ডারের সাথে কী কী যুক্ত', ['passport_holders']],
    // business questions — not ours
    ['how many tickets did we sell this month', null],
    ['what is our cash position', null],
    ['who is absent today', null],
    ['show me the aging', null],
    ['কত টাকা বাকি আছে', null],
    ['what is the status of Rabbi visa', null],
    ['top 5 customers by sales', null],
    ['what is the vat rate', null],
];
$pass = 0;
foreach ($cases as [$q, $want]) {
    $r = SchemaSense::strong($q) ?? SchemaSense::columnQuestion($q);
    $text = $r['text'] ?? '';
    $ok = $want === null ? $r === null : ($r !== null && array_reduce($want, fn($c, $w) => $c && str_contains($text, $w), true));
    // a Bangla question answered in Bangla, with Bangla numerals — [0-9], not \d: under /u \d matches ২০২৬ too
    if ($ok && $r && preg_match('/\p{Bengali}/u', $q)) $ok = $r['lang'] === 'bn' && !preg_match('/[0-9]+টি/u', $text);
    if ($ok) $pass++;
    if (!$ok || !$fail) printf("%s %-50s → %s\n", $ok ? 'ok  ' : 'FAIL', $q, $r ? mb_substr($text, 0, 110) : '(not mine)');
}
printf("%s\nTOTAL  %d/%d  %.1f%%\n", str_repeat('-', 60), $pass, count($cases), 100 * $pass / count($cases));
exit($pass === count($cases) ? 0 : 1);
