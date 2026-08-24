<?php
declare(strict_types=1);

/* ============================================================
   Sheet — EON hands over a file, without Python.

   "Export the payroll to excel" answered with the payroll and no
   file, and the published reason was that Python is not reachable
   on this host, so `export_report` had no route. That reason was
   never good enough: a CSV is a text file, and an XLSX is a zip of
   five small XML parts, both of which PHP has written since before
   the ERP existed. Nothing here needs a library, a binary, or a
   network call — which keeps the whole point of EON intact, since
   an export that leaves the server is an export that leaks the
   books.

   XLSX is written when ZipArchive is compiled in and CSV when it
   is not, and the answer says which it gave rather than promising
   a spreadsheet and delivering a text file. The CSV carries a
   UTF-8 BOM, because without it Excel on a Bangladeshi desktop
   opens বাংলা as mojibake and the file looks broken rather than
   the opener looking wrong.

   File names match what `api/file.php` already agrees to serve:
   report-<kind>-YYYYMMDD-HHMMSS.(xlsx|csv)
   ============================================================ */
final class Sheet
{
    public const KINDS = ['receivables', 'payables', 'payroll', 'expenses', 'attendance', 'pnl', 'staff'];

    /** the kind a word in the question means, or null */
    public static function kindOf(string $n): ?string
    {
        foreach ([
            'payroll'     => ['payroll', 'salary', 'salaries', 'payslip', 'payslips', 'বেতন', 'পে-স্লিপ', 'beton'],
            'receivables' => ['receivable', 'receivables', 'debtor', 'debtors', 'who owes', 'পাওনা', 'বকেয়া', 'pawna'],
            'payables'    => ['payable', 'payables', 'creditor', 'creditors', 'we owe', 'দেনা', 'দেয়', 'dena'],
            'expenses'    => ['expense', 'expenses', 'spending', 'খরচ', 'ব্যয়', 'khoroch'],
            'attendance'  => ['attendance', 'presence', 'হাজিরা', 'উপস্থিতি', 'hajira'],
            'staff'       => ['staff', 'employee', 'employees', 'headcount', 'people', 'কর্মী', 'জনবল'],
            'pnl'         => ['profit', 'loss', 'p&l', 'pnl', 'income statement', 'মুনাফা', 'লাভ', 'lav'],
        ] as $kind => $cues) {
            foreach ($cues as $c) if (Nlu::mentions($n, $c)) return $kind;
        }
        return null;
    }

    /* ---------------- what goes in the file ---------------- */

    /**
     * The sheet, and the sentence EON says about it.
     *
     * `title` and `note` are carried in both languages, because the answer quotes
     * them: built in English only, "খরচের এক্সেল ফাইল দাও" came back as বাংলা with
     * "Expenses 2026-08" and a whole English caveat inside it — the oldest trap in
     * this codebase, one layer further out than it has been caught before. The
     * column headers stay English: they are the workbook's, not the answer's, and
     * a spreadsheet gets passed to an accountant who may not read বাংলা.
     *
     * @return array{title:string,title_bn:string,headers:array<string>,rows:array<array<mixed>>,note:string,note_bn:string}
     */
    public static function build(string $kind, Analytics $A, ?string $month = null, ?array $scope = null): array
    {
        $month = $month ?: substr($A->today(), 0, 7);
        $keep = fn(array $r): bool => $scope === null || Scope::keeps($scope, $r);
        $mEn = class_exists('Phrase') ? Phrase::monthName($month, 'en') : $month;
        $mBn = class_exists('Phrase') ? Phrase::monthName($month, 'bn') : $month;

        switch ($kind) {
            case 'receivables':
            case 'payables':
                $type = $kind === 'receivables' ? 'receive' : 'pay';
                $s = $A->schedules($type);
                $rows = [];
                foreach (($s['by_party'] ?? []) as $p) {
                    $rows[] = [$p['party_name'], $p['party_type'], (float) $p['due'], (float) $p['overdue'],
                               (int) $p['count'], (int) $p['oldest']];
                }
                /* The gross and the ledger disagree, and the sheet must carry both or it
                   sends somebody chasing money that is already banked. */
                $note = $kind === 'receivables'
                    ? sprintf('Gross open invoices %s; the party ledger supports %s, with %s held as advances. %d parties where the two disagree are listed at the foot.',
                        self::tk($s['total']), self::tk($s['ledger_receivable']), self::tk($s['advances_held']), count($s['reconciliation'] ?? []))
                    : sprintf('Open payables %s, of which %s is past its date.', self::tk($s['total']), self::tk($s['overdue_total']));
                $noteBn = $kind === 'receivables'
                    ? sprintf('মোট খোলা ইনভয়েস %s; পার্টি খতিয়ান সমর্থন করে %s, আর %s অগ্রিম হিসেবে জমা আছে। যে %s জন পার্টির দুই হিসাব মিলছে না, তারা নিচে আলাদা করা আছে।',
                        self::tkBn($s['total']), self::tkBn($s['ledger_receivable']), self::tkBn($s['advances_held']), self::nBn(count($s['reconciliation'] ?? [])))
                    : sprintf('খোলা দেনা %s, তার %s তারিখ পেরিয়ে গেছে।', self::tkBn($s['total']), self::tkBn($s['overdue_total']));
                foreach (($s['reconciliation'] ?? []) as $r) {
                    $rows[] = ['— disagrees: ' . $r['party'], $r['reason'], (float) $r['invoiced_open'], (float) $r['ledger_balance'], '', ''];
                }
                return ['title' => $kind === 'receivables' ? 'Receivables by party' : 'Payables by party',
                        'title_bn' => $kind === 'receivables' ? 'পার্টি অনুযায়ী পাওনা' : 'পার্টি অনুযায়ী দেনা',
                        'headers' => ['Party', 'Type', 'Due', 'Overdue', 'Items', 'Oldest (days)'],
                        'rows' => $rows, 'note' => $note, 'note_bn' => $noteBn];

            case 'payroll':
                /* Payroll is generated a month behind, so the current month is usually
                   empty. Exporting an empty August when July is what exists reads as a
                   broken export — `a_payroll` already falls back to the last closed
                   month, and the sheet says which month it actually holds. */
                $keys = [];
                foreach ($A->rows('payroll') as $p) if (($p['month_key'] ?? '') !== '') $keys[(string) $p['month_key']] = true;
                $mk = $month;
                if (!isset($keys[$mk]) && $keys !== []) { $ks = array_keys($keys); sort($ks); $mk = (string) end($ks); }
                $rows = [];
                foreach ($A->rows('payroll') as $p) {
                    if (($p['month_key'] ?? '') !== $mk) continue;
                    $rows[] = [$A->employeeName($p['user_id']), $A->companyName($p['company_id']),
                               (float) $p['gross_salary'], (float) $p['total_deductions'], (float) $p['overtime_salary'],
                               (float) $p['net_salary'], (string) $p['status'], (string) ($p['payment_date'] ?? '')];
                }
                $pEn = class_exists('Phrase') ? Phrase::monthName($mk, 'en') : $mk;
                $pBn = class_exists('Phrase') ? Phrase::monthName($mk, 'bn') : $mk;
                return ['title' => 'Payroll ' . $pEn, 'title_bn' => $pBn . ' মাসের বেতন',
                        'headers' => ['Employee', 'Company', 'Gross', 'Deductions', 'Overtime', 'Net', 'Status', 'Paid on'],
                        'rows' => $rows,
                        'note' => sprintf('%d payslips for %s. Deductions are what the ERP stored, not a recomputation.', count($rows), $pEn),
                        'note_bn' => sprintf('%s মাসের %s টা পে-স্লিপ। কর্তনগুলো ERP-তে যা লেখা আছে তাই, নতুন করে হিসাব করা নয়।', $pBn, self::nBn(count($rows)))];

            case 'expenses':
                $rows = [];
                foreach ($A->rows('expenses') as $e) {
                    if (substr((string) ($e['expense_date'] ?? ''), 0, 7) !== $month) continue;
                    if (!$keep($e)) continue;
                    $rows[] = [(string) $e['expense_date'], (string) $e['title'], (string) $e['category'],
                               (string) ($e['department'] ?? ''), $A->companyName($e['company_id']),
                               (float) $e['amount'], (string) ($e['payment_mode'] ?? ''), (string) ($e['approval_status'] ?? '')];
                }
                return ['title' => 'Expenses ' . $mEn, 'title_bn' => $mBn . ' মাসের খরচ',
                        'headers' => ['Date', 'Title', 'Category', 'Department', 'Company', 'Amount', 'Paid by', 'Approval'],
                        'rows' => $rows,
                        'note' => 'An expense posts to the ledger only once it is approved — unapproved rows are in this sheet and not in the accounts.',
                        'note_bn' => 'খরচ অনুমোদন হলে তবেই খতিয়ানে ওঠে — যেগুলো অনুমোদন হয়নি সেগুলো এই শিটে আছে, হিসাবে নেই।'];

            case 'attendance':
                $rows = [];
                foreach ($A->rows('attendances') as $a) {
                    if (substr((string) ($a['date'] ?? ''), 0, 7) !== $month) continue;
                    $rows[] = [(string) $a['date'], $A->employeeName($a['user_id']), (string) ($a['check_in'] ?? ''),
                               (string) ($a['check_out'] ?? ''), (string) ($a['status'] ?? ''),
                               (int) ($a['late_minutes'] ?? 0), (int) ($a['overtime_minutes'] ?? 0)];
                }
                $t = $A->attendanceToday();
                return ['title' => 'Attendance ' . $mEn, 'title_bn' => $mBn . ' মাসের হাজিরা',
                        'headers' => ['Date', 'Employee', 'In', 'Out', 'Status', 'Late (min)', 'Overtime (min)'],
                        'rows' => $rows,
                        'note' => sprintf('Only %d of the active staff are enrolled on the attendance device, so this covers the tracked base, not the whole payroll.', (int) ($t['total'] ?? 0)),
                        'note_bn' => sprintf('হাজিরা মেশিনে সক্রিয় কর্মীদের মধ্যে মাত্র %s জন নথিভুক্ত, তাই এটা ট্র্যাক হওয়া অংশটুকুর হিসাব, পুরো পে-রোলের নয়।', self::nBn((int) ($t['total'] ?? 0)))];

            case 'staff':
                $rows = [];
                foreach ($A->rows('employees') as $e) {
                    if (($e['status'] ?? 'active') !== 'active') continue;
                    if (!$keep($e)) continue;
                    $rows[] = [(string) $e['name'], (string) ($e['designation'] ?? ''), (string) ($e['department'] ?? ''),
                               $A->companyName($e['company_id']), (string) ($e['joining_date'] ?? ''),
                               (float) ($e['salary'] ?? 0), (string) ($e['employment_type'] ?? '')];
                }
                return ['title' => 'Active staff', 'title_bn' => 'সক্রিয় কর্মীর তালিকা',
                        'headers' => ['Name', 'Designation', 'Department', 'Company', 'Joined', 'Salary', 'Type'],
                        'rows' => $rows,
                        'note' => 'Active employees only. A blank department is a gap in the records, not a department.',
                        'note_bn' => 'শুধু সক্রিয় কর্মী। বিভাগের ঘর ফাঁকা মানে রেকর্ডের ফাঁক, কোনো বিভাগ নয়।'];

            case 'pnl':
            default:
                $pl = $A->profitAndLoss($month . '-01', $month . '-31');
                $rows = [];
                foreach (($pl['top_income'] ?? []) as $r) $rows[] = ['Income', $r['code'], $r['name'], (float) $r['amount']];
                foreach (($pl['top_opex'] ?? []) as $r) $rows[] = ['Overhead', $r['code'], $r['name'], (float) $r['amount']];
                $rows[] = ['', '', 'Income', (float) $pl['income']];
                $rows[] = ['', '', 'Direct cost', (float) $pl['direct_cost']];
                $rows[] = ['', '', 'Overheads', (float) $pl['opex']];
                $rows[] = ['', '', 'Net profit', (float) $pl['net_profit']];
                $note = $pl['ledger_covers_pct'] !== null
                    ? sprintf('The desks invoiced %s in this period and the ledger carries %d%% of it — %s has not been journalised, so every figure here is short by that much.',
                        self::tk($pl['sales_invoiced']), (int) $pl['ledger_covers_pct'], self::tk($pl['unposted_sales']))
                    : 'Read off the ledger.';
                $noteBn = $pl['ledger_covers_pct'] !== null
                    ? sprintf('এই সময়ে ডেস্কগুলো ইনভয়েস করেছে %s, খতিয়ানে আছে তার %s%% — %s এখনো জার্নাল হয়নি, তাই এখানকার প্রতিটা সংখ্যা ঠিক ততটাই কম।',
                        self::tkBn($pl['sales_invoiced']), self::nBn((int) $pl['ledger_covers_pct']), self::tkBn($pl['unposted_sales']))
                    : 'খতিয়ান থেকে পড়া।';
                return ['title' => 'Profit and loss ' . $mEn, 'title_bn' => $mBn . ' মাসের লাভ-লোকসান',
                        'headers' => ['Section', 'Code', 'Line', 'Amount'],
                        'rows' => $rows, 'note' => $note, 'note_bn' => $noteBn];
        }
    }

    private static function tk($v): string { return '৳' . number_format((float) $v); }

    /* A Bangla sentence counts in Bengali digits. Sharing the English formatter left
       "জুলাই ২০২৬ মাসের 11 টা পে-স্লিপ" and "৳1,552,800" inside বাংলা notes — the same
       leak as everywhere else, and it reaches the answer because the answer quotes
       these strings verbatim. */
    private static function tkBn($v): string
    {
        return class_exists('Phrase') ? Phrase::money((float) $v, 'bn') : self::tk($v);
    }
    private static function nBn($v): string
    {
        return class_exists('Phrase') ? Phrase::n($v, 'bn') : (string) $v;
    }

    /* ---------------- writing it ---------------- */

    /**
     * @return array{ok:bool,file:string,name:string,format:string,rows:int,error?:string}
     */
    public static function write(array $sheet, string $kind): array
    {
        $dir = EON_ROOT . '/storage/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return ['ok' => false, 'file' => '', 'name' => '', 'format' => '', 'rows' => 0,
                    'error' => 'storage/data is not writable'];
        }
        $slug = preg_replace('/[^a-z]/', '', strtolower($kind)) ?: 'report';
        $stamp = date('Ymd-His');
        $useZip = class_exists('ZipArchive');
        $name = sprintf('report-%s-%s.%s', $slug, $stamp, $useZip ? 'xlsx' : 'csv');
        $path = $dir . '/' . $name;

        $ok = $useZip ? self::xlsx($path, $sheet) : self::csv($path, $sheet);
        if (!$ok) return ['ok' => false, 'file' => '', 'name' => '', 'format' => '', 'rows' => 0,
                          'error' => 'could not write the file'];

        return ['ok' => true, 'file' => $path, 'name' => $name,
                'format' => $useZip ? 'xlsx' : 'csv', 'rows' => count($sheet['rows'])];
    }

    /** CSV with a BOM — without it Excel reads বাংলা as mojibake and blames the file */
    private static function csv(string $path, array $sheet): bool
    {
        $fh = @fopen($path, 'wb');
        if ($fh === false) return false;
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [$sheet['title']]);
        if (($sheet['note'] ?? '') !== '') fputcsv($fh, [$sheet['note']]);
        fputcsv($fh, []);
        fputcsv($fh, $sheet['headers']);
        foreach ($sheet['rows'] as $r) fputcsv($fh, $r);
        fclose($fh);
        return true;
    }

    /**
     * XLSX by hand: the five parts Excel insists on, inline strings so there is no
     * shared-string table to keep in step, and numbers written as numbers so the
     * sheet can be summed rather than retyped.
     */
    private static function xlsx(string $path, array $sheet): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $tab = self::xmlAttr(mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $sheet['title']) ?? 'Sheet1', 0, 31));
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $tab . '" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        $lines = [];
        $lines[] = [$sheet['title']];
        if (($sheet['note'] ?? '') !== '') $lines[] = [$sheet['note']];
        $lines[] = [];
        $lines[] = $sheet['headers'];
        foreach ($sheet['rows'] as $r) $lines[] = $r;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($lines as $i => $row) {
            $rn = $i + 1;
            $xml .= '<row r="' . $rn . '">';
            $c = 0;
            foreach ($row as $v) {
                $ref = self::col($c++) . $rn;
                if (is_int($v) || is_float($v)) {
                    $xml .= '<c r="' . $ref . '"><v>' . (is_float($v) ? rtrim(rtrim(sprintf('%.4F', $v), '0'), '.') ?: '0' : $v) . '</v></c>';
                } else {
                    $s = (string) $v;
                    if ($s === '') { $xml .= '<c r="' . $ref . '"/>'; continue; }
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlText($s) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

        return $zip->close();
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function col(int $i): string
    {
        $s = '';
        for ($n = $i; $n >= 0; $n = intdiv($n, 26) - 1) {
            $s = chr(65 + $n % 26) . $s;
        }
        return $s;
    }

    /* XML 1.0 forbids most control characters outright, and one stray 0x0B in a
       remark field makes Excel call the whole workbook corrupt rather than skip
       the cell — so they are stripped before escaping, not after. */
    private static function xmlText(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? $s;
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function xmlAttr(string $s): string
    {
        return self::xmlText($s);
    }
}
