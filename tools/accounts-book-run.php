<?php
declare(strict_types=1);

/* ============================================================
   accounts-book-run.php — the account book, measured.

   Asserts, for every case:
     entry     the question retrieves the entry it is about
     lang      it answers in the language asked, and a বাংলা
               answer carries no Latin letter at all
     brain     through Brain::askOffline the book is what answers
               (the figure answerers must not eat a bookkeeping
               question, and the book must not eat a figure one)

   And over the book itself:
     shape     every entry has cues in three scripts, both
               answers, a source, a screen
     no rate   no entry states a percentage for a tax

     php tools/accounts-book-run.php           all of it
     php tools/accounts-book-run.php --fail    only what broke
     php tools/accounts-book-run.php --show    print the answers
   ============================================================ */

define('EON_ROOT', dirname(__DIR__) . '/server');
require_once EON_ROOT . '/bootstrap.php';

$opt = getopt('', ['fail', 'show']);
$onlyFail = isset($opt['fail']);
$show = isset($opt['show']);

if (!class_exists('AccountsBook') || !AccountsBook::available()) {
    fwrite(STDERR, "server/kb/accounts-book.json is missing or empty\n");
    exit(2);
}

/* q, language, entry it must land on. `brain` => true also runs it through askOffline */
$CASES = [
    ['how is a ticket sale posted',                          'en', 'ticket-sale', true],
    ['টিকিট বিক্রি কীভাবে পোস্ট হয়',                          'bn', 'ticket-sale', true],
    ['ticket sale er journal ki',                            'bn', 'ticket-sale', false],
    ['what is the journal for a ticket purchase',            'en', 'ticket-purchase', true],
    ['টিকিট কেনার এন্ট্রি কী',                                'bn', 'ticket-purchase', true],
    ['how is the airline commission posted',                 'en', 'ticket-purchase', false],
    ['how is a ticket refund posted',                        'en', 'ticket-refund-void', true],
    ['টিকিট রিফান্ডের এন্ট্রি কীভাবে হয়',                      'bn', 'ticket-refund-void', true],
    ['who pays the reissue penalty, what is the entry',      'en', 'ticket-reissue', false],
    ['রিইস্যুর এন্ট্রি কী',                                   'bn', 'ticket-reissue', true],
    ['how is a contract flight posted',                      'en', 'contract-flight-passenger', true],
    ['কন্ট্রাক্ট ফ্লাইট কীভাবে পোস্ট হয়',                      'bn', 'contract-flight-passenger', true],
    ['what happens when a passenger is deported',            'en', 'contract-flight-cancel-deport-offload', false],
    ['ডিপোর্ট হলে হিসাব কী হয়',                              'bn', 'contract-flight-cancel-deport-offload', false],
    ['how is a contract file posted',                        'en', 'contract-file', true],
    ['how is a visa posted',                                 'en', 'visa', true],
    ['ভিসা কীভাবে পোস্ট হয়',                                 'bn', 'visa', true],
    ['which ledger does an agent live in',                   'en', 'party-types-ledgers', false],
    ['এজেন্টের লেজার কোথায়',                                 'bn', 'party-types-ledgers', false],
    ['how to read the party statement',                      'en', 'party-statement-read', false],
    ['পার্টি স্টেটমেন্ট কীভাবে পড়ব',                           'bn', 'party-statement-read', false],
    ['what is an unallocated payment',                       'en', 'party-payment-advance', false],
    ['how is a payment posted from an agent',                'en', 'party-payment-advance', true],
    ['গ্রাহকের অগ্রিম কীভাবে পোস্ট হয়',                        'bn', 'party-payment-advance', false],
    ['why does the invoice still show due',                  'en', 'trap-advances-not-applied', false],
    ['টাকা দিয়েছে তবু বকেয়া কেন',                              'bn', 'trap-advances-not-applied', false],
    ['why is the trial balance off',                         'en', 'trap-unbalanced-journal', false],
    ['ট্রায়াল ব্যালান্স মিলছে না কেন',                           'bn', 'trap-unbalanced-journal', false],
    ['how to enter opening balance',                         'en', 'opening-balance', false],
    ['ওপেনিং ব্যালান্সের এন্ট্রি কী',                           'bn', 'opening-balance', true],
    ['how is salary posted',                                 'en', 'payroll', true],
    ['বেতন কীভাবে পোস্ট হয়',                                 'bn', 'payroll', true],
    ['how is an advance salary posted',                      'en', 'advance-salary', true],
    ['অগ্রিম বেতন কীভাবে পোস্ট হয়',                           'bn', 'advance-salary', true],
    ['how does petty cash work',                             'en', 'petty-cash', false],
    ['পেটি ক্যাশ কীভাবে চলে',                                 'bn', 'petty-cash', false],
    ['how is an expense posted',                             'en', 'expense-reimbursement', true],
    ['কর্মী নিজের টাকায় খরচ করলে এন্ট্রি কী',                   'bn', 'expense-reimbursement', true],
    ['how is a bank transfer posted',                        'en', 'bank-transfer', true],
    ['ব্যাংক ট্রান্সফারের এন্ট্রি কী',                           'bn', 'bank-transfer', true],
    ['what is the owner drawings entry',                     'en', 'owner-capital-drawings-loans', true],
    ['ঋণের কিস্তির এন্ট্রি কী',                                'bn', 'owner-capital-drawings-loans', true],
    ['how is a discount posted',                             'en', 'party-discount', true],
    ['how is an invoice posted',                             'en', 'custom-invoice', true],
    ['how does a payment schedule post',                     'en', 'payment-schedule', false],
    ['how to read the balance sheet',                        'en', 'reports-read', true],
    ['লাভ-ক্ষতি কীভাবে হিসাব হয়',                             'bn', 'reports-read', false],
    ['how to correct a wrong entry',                         'en', 'correct-a-journal', true],
    ['ভুল এন্ট্রি কীভাবে ঠিক করব',                             'bn', 'correct-a-journal', true],
    ['where is vat posted',                                  'en', 'tax-accounts', false],
    ['what is double entry',                                 'en', 'double-entry-epal', true],
    ['ডেবিট ক্রেডিট কী',                                     'bn', 'double-entry-epal', true],
    ['how are accounts numbered in the chart of accounts',   'en', 'chart-of-accounts', true],
];

/* figure questions the book must leave alone */
$LEAVE = [
    'how much did we sell this month', 'what is our cash position', 'who owes us the most',
    'show me the trial balance', 'এই মাসে বিক্রি কত', 'আজ কে অনুপস্থিত', 'payroll this month',
    'what is the capital of Bangladesh',
];

$D = null; $T = null;
$needBrain = (bool) array_filter($CASES, fn($c) => $c[3]);
if ($needBrain) {
    fwrite(STDERR, "loading dataset…\n");
    $D = Dataset::current(null, false);
    $T = new Tools($D, null);
}

$pass = 0; $total = 0;
$report = function (bool $ok, string $label, string $why, string $text = '') use (&$pass, &$total, $onlyFail, $show) {
    $total++;
    if ($ok) $pass++;
    if ($ok && $onlyFail) return;
    printf("  %s  %-52s %s\n", $ok ? 'ok  ' : 'FAIL', mb_strimwidth($label, 0, 52, '…'), $why);
    if ($show && $text !== '') echo '        ' . mb_strimwidth($text, 0, 300, '…') . "\n";
};

echo "== the book\n";
foreach (AccountsBook::entries() as $e) {
    $miss = [];
    foreach (['en', 'bn', 'bl'] as $s) if (empty($e['cues'][$s])) $miss[] = "cues.$s";
    foreach (['answer_en', 'answer_bn', 'source', 'topic', 'scope'] as $f) if (empty($e[$f])) $miss[] = $f;
    if (empty($e['where']['screen'])) $miss[] = 'where.screen';
    if (preg_match('/[A-Za-z]/', (string) ($e['answer_bn'] ?? ''))) $miss[] = 'Latin in answer_bn';
    /* a rate the ERP does not hold must not be said; the one percentage allowed is a share EON measured (42%) */
    if (preg_match('/(vat|tax|tds|vds|ভ্যাট|কর)/iu', (string) $e['topic'] . ' ' . $e['id'])
        && preg_match('/\d\s*(%|percent)|[০-৯]\s*(%|শতাংশ)/u', (string) $e['answer_en'] . ' ' . (string) $e['answer_bn'])) $miss[] = 'states a tax rate';
    $report(!$miss, $e['id'], $miss ? implode(', ', $miss) : 'complete');
}

echo "== retrieval\n";
foreach ($CASES as [$q, $lang, $id]) {
    $r = AccountsBook::answer($q, $lang === 'bn' ? 'bn-BD' : 'en-US');
    $why = [];
    if ($r === null) $why[] = 'nothing retrieved';
    else {
        if ($r['id'] !== $id) $why[] = "got {$r['id']}";
        if ($r['lang'] !== $lang) $why[] = "answered in {$r['lang']}";
        if ($lang === 'bn' && preg_match('/[A-Za-z]/', $r['text'])) $why[] = 'Latin inside the বাংলা answer';
    }
    $report(!$why, "[$lang] $q", $why ? implode('; ', $why) : $id, $r['text'] ?? '');
}

if ($needBrain) {
    echo "== through the brain\n";
    foreach ($CASES as [$q, $lang, $id, $brain]) {
        if (!$brain) continue;
        $r = Brain::askOffline($q, $D, null, $T, $lang === 'en' ? 'en-US' : 'bn-BD');
        $got = $r['book']['id'] ?? null;
        $why = $got === $id ? $id : ('answered by ' . ($r['intent'] ?? '?') . ($got ? " ($got)" : ''));
        $report($got === $id, "[$lang] $q", $why, $r['text'] ?? '');
    }
    echo "== left alone\n";
    foreach ($LEAVE as $q) {
        $r = Brain::askOffline($q, $D, null, $T, preg_match('/[\x{0980}-\x{09FF}]/u', $q) ? 'bn-BD' : 'en-US');
        $got = $r['book']['id'] ?? null;
        $report($got === null, $q, $got ? "taken by the book ($got)" : 'answered by ' . ($r['intent'] ?? '?'), $r['text'] ?? '');
    }
}

echo str_repeat('=', 72) . "\n";
printf("TOTAL  %d/%d  %.1f%%\n", $pass, $total, $total ? 100 * $pass / $total : 0);
exit($pass === $total ? 0 : 1);
