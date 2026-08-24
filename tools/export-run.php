<?php
declare(strict_types=1);

/* ============================================================
   export-run.php — EON hands over a file, and the file opens.

   "No file out" was published against EON with Python named as
   the reason. `Sheet` writes the workbook in plain PHP, so this
   runner has to prove three separate things, and the middle one
   is where a home-made xlsx writer usually dies:

     1. the answer names the right kind and carries a link
     2. the artefact is a real workbook — a zip whose five parts
        all parse as XML, holding as many rows as were claimed.
        A file that exists is not a workbook; Excel calls a
        malformed one corrupt and refuses the lot.
     3. a বাংলা export answers in বাংলা all the way through,
        digits included. The sheet's title and note are quoted
        straight into the sentence, so an English-only note is a
        leak one layer further out than the usual one.

   And the refusals: an export with no subject must ask which,
   never guess, and an empty period must not write an empty file
   that reads as a broken export.

     php tools/export-run.php          all of it
     php tools/export-run.php --fail   only what broke
   ============================================================ */

$root = dirname(__DIR__);
define('EON_ROOT', $root . '/server');
$opt = getopt('', ['fail']);
$onlyFail = isset($opt['fail']);

foreach (['Config', 'Log', 'Db', 'Dataset', 'Erp', 'Memory', 'Analytics', 'ErpMap',
          'Scope', 'Sheet', 'Grammar', 'Nlu', 'Phrase', 'Kb', 'Loc', 'Speech', 'Insight',
          'Reason', 'Tools', 'Answer'] as $c) {
    $f = EON_ROOT . '/lib/' . $c . '.php';
    if (is_file($f)) require_once $f;
}
foreach (['Sheet', 'Nlu', 'Answer', 'Analytics'] as $need) {
    if (!class_exists($need)) { fwrite(STDERR, "missing class $need\n"); exit(2); }
}

$cache = $root . '/tools/.qa-dataset.json';
$D = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
if (!is_array($D)) { fwrite(STDERR, "no dataset cache — run: php tools/qa-run.php --refresh\n"); exit(2); }

Nlu::useDataset($D);
$A = new Analytics($D);
$tools = new Tools($D);

$pass = 0; $fail = 0; $out = [];
$say = function (bool $ok, string $what, string $why = '') use (&$pass, &$fail, &$out, $onlyFail) {
    $ok ? $pass++ : $fail++;
    if (!$ok || !$onlyFail) $out[] = sprintf('%s %-56s %s', $ok ? '  ok  ' : ' FAIL ', mb_strimwidth($what, 0, 56, '…'), $why);
};

/* ---------- 1 & 2. every kind builds, writes, and opens ---------- */

$written = [];
foreach (Sheet::KINDS as $kind) {
    $sheet = Sheet::build($kind, $A);
    $w = Sheet::write($sheet, $kind);
    if (!($w['ok'] ?? false)) { $say(false, "write: $kind", (string) ($w['error'] ?? 'write failed')); continue; }
    $written[] = $w['file'];

    if (!preg_match('/^report-[a-z]+-\d{8}-\d{6}\.(xlsx|csv)$/', $w['name'])) {
        $say(false, "name: $kind", "api/file.php will refuse '{$w['name']}'");
        continue;
    }

    if ($w['format'] === 'xlsx') {
        $z = new ZipArchive();
        if ($z->open((string) $w['file']) !== true) { $say(false, "open: $kind", 'not a readable zip'); continue; }
        $bad = [];
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml',
                  'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml'] as $part) {
            $xml = $z->getFromName($part);
            if ($xml === false) { $bad[] = "missing $part"; continue; }
            libxml_use_internal_errors(true); libxml_clear_errors();
            if (simplexml_load_string($xml) === false) {
                $e = libxml_get_errors()[0]->message ?? 'unparseable';
                $bad[] = "$part: " . trim((string) $e);
            }
        }
        $sx = @simplexml_load_string((string) $z->getFromName('xl/worksheets/sheet1.xml'));
        $rowsInFile = $sx === false ? 0 : count($sx->sheetData->row);
        $z->close();
        if ($rowsInFile < count($sheet['rows'])) $bad[] = sprintf('claimed %d rows, wrote %d', count($sheet['rows']), $rowsInFile);
        $say($bad === [], "workbook: $kind", $bad === [] ? sprintf('%d rows, valid xlsx', count($sheet['rows'])) : implode('; ', $bad));
    } else {
        $body = (string) @file_get_contents((string) $w['file']);
        $bomOk = str_starts_with($body, "\xEF\xBB\xBF");
        $say($bomOk, "csv: $kind", $bomOk ? 'BOM present' : 'no BOM — Excel will mangle বাংলা');
    }

    // both languages must be carried, or the answer leaks English into বাংলা
    $hasBn = ($sheet['title_bn'] ?? '') !== '' && ($sheet['note_bn'] ?? '') !== '';
    $say($hasBn, "both languages: $kind", $hasBn ? '' : 'title_bn/note_bn missing — the answer quotes these');
}

/* ---------- 3. the answers ---------- */

$ASK = [
    ['q' => 'export the payroll to excel',        'kind' => 'payroll',     'lang' => 'en'],
    ['q' => 'download the receivables as a sheet', 'kind' => 'receivables', 'lang' => 'en'],
    ['q' => 'give me the staff list as excel',     'kind' => 'staff',       'lang' => 'en'],
    ['q' => 'export the expenses to csv',          'kind' => 'expenses',    'lang' => 'en'],
    ['q' => 'export the p&l',                      'kind' => 'pnl',         'lang' => 'en'],
    ['q' => 'বেতনের এক্সেল দাও',                    'kind' => 'payroll',     'lang' => 'bn'],
    ['q' => 'খরচের এক্সেল ফাইল দাও',                 'kind' => 'expenses',    'lang' => 'bn'],
    ['q' => 'হাজিরার ফাইল করে দাও',                  'kind' => 'attendance',  'lang' => 'bn'],
];

foreach ($ASK as $c) {
    $parse = Nlu::parse($c['q'], $c['lang']);
    $r = Answer::compose($c['q'], $parse, $A, $tools);
    $text = (string) $r['text'];
    $why = [];

    if (($r['intent'] ?? '') !== 'export') $why[] = 'did not route to export (' . ($r['intent'] ?? 'null') . ')';
    if (!str_contains($text, 'file.php?name=report-')) $why[] = 'no download link in the answer';
    if (!str_contains($text, 'report-' . $c['kind'] . '-')) $why[] = "exported something other than {$c['kind']}";

    /* A বাংলা answer must be বাংলা all the way through — and the digits count.
       This is the assertion that caught the sheet's English-only note being
       quoted verbatim into a Bangla sentence. The URL is exempt: a file name
       is a file name in every language. */
    if ($c['lang'] === 'bn') {
        $prose = preg_replace('/\S*file\.php\S*/u', '', $text) ?? $text;
        $prose = str_replace(['XLSX', 'CSV', 'ERP'], '', $prose);       // format and product names stay
        if (preg_match('/[A-Za-z]{3,}/u', $prose, $m)) $why[] = "English fragment in a বাংলা answer: '{$m[0]}'";
        /* `\d` under /u matches every Unicode decimal, ২০২৬ included, so the first
           version of this check failed three correct answers for containing a
           correctly-Bengali year. Latin digits have to be named as [0-9]. */
        if (preg_match('/(?<![0-9.])[0-9]{2,}/u', $prose, $m)) $why[] = "Latin digits in a বাংলা answer: '{$m[0]}'";
    }

    $say($why === [], $c['q'], $why === [] ? $c['kind'] : implode('; ', $why));
}

/* ---------- 4. the refusals ---------- */

foreach ([['export it', 'asks which'], ['download that', 'asks which']] as [$q, $label]) {
    $r = Answer::compose($q, Nlu::parse($q, 'en'), $A, $tools);
    $t = (string) $r['text'];
    $asks = str_contains($t, 'which one') || str_contains($t, 'not until I know');
    $wrote = str_contains($t, 'file.php?name=');
    $say($asks && !$wrote, "no subject: \"$q\"",
         $asks && !$wrote ? $label : ($wrote ? 'guessed a kind and wrote a file' : 'did not ask which'));
}

// an empty slice must not become an empty file
$sheet = Sheet::build('expenses', $A, '1999-01');
$say($sheet['rows'] === [], 'an empty period yields no rows', $sheet['rows'] === [] ? 'nothing to write' : 'invented rows');
$q = 'export the expenses for January 1999';
$r = Answer::compose($q, Nlu::parse($q, 'en'), $A, $tools);
$noFile = !str_contains((string) $r['text'], 'file.php?name=');
$say($noFile, 'and no empty file is written', $noFile ? 'refused, and said why' : 'wrote an empty workbook');

/* ---------- tidy up after ourselves ---------- */
foreach ($written as $f) @unlink($f);

$total = $pass + $fail;
echo "EON — the export, and whether the file actually opens\n";
echo str_repeat('=', 74) . "\n";
foreach ($out as $l) echo $l . "\n";
echo str_repeat('=', 74) . "\n";
printf("TOTAL  %d/%d  %.1f%%\n", $pass, $total, $total ? $pass / $total * 100 : 0);
exit($fail > 0 ? 1 : 0);
