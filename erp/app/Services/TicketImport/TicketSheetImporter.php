<?php

namespace App\Services\TicketImport;

use App\Models\Airline;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ticket Entry → Ticket Import → "Import CSV / Excel" (owner, 2026-10-19).
 *
 * A spreadsheet with one row per passenger ticket becomes the SAME result a
 * ticket PDF gives (TicketPdfImporter::blank()), one result per row — so the
 * matcher, the cards, the Complete-details table and Save are the PDF
 * import's own. Reads only; the file is never stored.
 *
 * Columns are found by their heading, in any order, under the names agency
 * sheets use ("PNR" / "Booking ref", "Pax name", "Sector", "Net fare" …).
 * Formulas are never calculated here — a formula cell gives what Excel last
 * showed. A value that cannot be read is left empty with a note on its card,
 * never guessed.
 */
class TicketSheetImporter
{
    public const MAX_ROWS = 300;

    private const MAX_COLS = 40;

    /** Rows searched for the heading row. */
    private const HEAD_ROWS = 15;

    /**
     * The daily sheet staff fill in (owner, 2026-10-19: "just single excel sheet … they will enter the
     * tickets there, and give everyday" — then: "do not make any multiple sheet, instruction etc. just
     * main data"): ONE sheet, a heading row, nothing else. The columns of the sheet they already keep,
     * minus what the ERP works out itself (Profit Loss) or what only repeated another column (Source =
     * Vendor Name, Office Ref., Segment), plus Passport No (the one certain match for a passenger) and
     * Return Date (round trips — it used to sit in Status). Heading → [width, number format]; every
     * heading must be known by field().
     *
     * Dates are TEXT cells: a month-first Excel turns a typed 4/10/2026 into 10 April (seen in the
     * owner's demo sheet); as text it stays what was typed (05.10.2026) and is read day-first here.
     * Ticket Number and Passport No are text too, so a leading zero survives.
     */
    public const TEMPLATE = [
        'Issue Date' => [12, '@'],
        'Flight Date' => [16, '@'],
        'Return Date' => [12, '@'],
        'PNR' => [11, '@'],
        'Pax Name' => [26, '@'],
        'Passport No' => [13, '@'],
        'Ticket Number' => [16, '@'],
        'Route' => [16, '@'],
        'Airline' => [9, '@'],
        'Agent Name' => [22, '@'],
        'Vendor Name' => [20, '@'],
        'Agent Price' => [12, '#,##0'],
        'Vendor Price' => [12, '#,##0'],
        'Remarks' => [24, '@'],
    ];

    /** Heading, normalised (lower case, letters and digits, single spaces) → field. */
    private const HEADINGS = [
        'pnr' => ['pnr', 'pnr no', 'pnr number', 'pnr code', 'ticket pnr', 'gds pnr', 'booking ref', 'booking reference',
            'booking code', 'booking id', 'reservation code', 'reservation no', 'record locator', 'locator'],
        'airline_pnr' => ['airline pnr', 'airline ref', 'airline reference', 'airline locator', 'airline booking ref'],
        'name' => ['passenger', 'passenger name', 'passengers', 'pax', 'pax name', 'name', 'traveller', 'traveler',
            'traveller name', 'traveler name', 'full name', 'passenger full name'],
        'surname' => ['surname', 'last name', 'family name'],
        'given' => ['given name', 'given names', 'first name', 'first names'],
        'title' => ['title', 'salutation'],
        'passport' => ['passport', 'passport no', 'passport number', 'ppt', 'ppt no'],
        'eticket' => ['e ticket', 'eticket', 'e ticket no', 'e ticket number', 'eticket no', 'eticket number', 'ticket no',
            'ticket number', 'tkt', 'tkt no', 'etkt', 'etkt no', 'document no', 'document number'],
        'pax_type' => ['pax type', 'passenger type', 'ptc'],
        'dob' => ['dob', 'date of birth', 'birth date'],
        'route' => ['route', 'route name', 'sector', 'sectors', 'routing', 'itinerary', 'journey'],
        'from' => ['from', 'origin', 'from airport', 'origin airport', 'departure airport', 'dep airport', 'from city',
            'from airport code', 'from code', 'origin code'],
        'to' => ['to', 'destination', 'to airport', 'destination airport', 'arrival airport', 'arr airport', 'to city',
            'to airport code', 'to code', 'destination code'],
        'via' => ['via', 'transit', 'via airport', 'transit airport', 'stops'],
        'carrier' => ['airline', 'airlines', 'carrier', 'airline name', 'airline code', 'validating carrier', 'plating carrier'],
        'flight_no' => ['flight', 'flights', 'flight no', 'flight number', 'flight nos', 'flight numbers', 'flt', 'flt no'],
        'dep' => ['departure', 'departure date', 'dep date', 'travel date', 'flight date', 'date of travel', 'journey date',
            'date of journey', 'doj', 'departure date time', 'departure datetime', 'departure on', 'departure at'],
        'dep_time' => ['departure time', 'dep time', 'flight time', 'etd', 'std'],
        'arr' => ['arrival', 'arrival date', 'arrival time', 'arr date', 'arr time', 'arrival date time', 'arrival at', 'eta', 'sta'],
        'return_dep' => ['return', 'return date', 'return departure', 'return travel date', 'return flight date', 'return on', 'return at'],
        'return_flight' => ['return flight', 'return flight no', 'return flight number'],
        'cabin' => ['class', 'cabin', 'cabin class', 'travel class', 'ticket class'],
        'rbd' => ['rbd', 'booking class', 'fare class', 'sub class', 'subclass'],
        'baggage' => ['baggage', 'bag', 'baggage allowance', 'luggage', 'checked baggage'],
        'issue_date' => ['issue date', 'issued', 'issued on', 'issuing date', 'date of issue', 'purchase date', 'ticket issue date'],
        'base' => ['base fare', 'base', 'basic fare', 'base amount'],
        'tax' => ['tax', 'taxes', 'tax amount', 'total tax', 'taxes fees', 'tax fees'],
        'tax_breakdown' => ['tax breakdown', 'tax details'],
        'total' => ['net cost', 'cost', 'net fare', 'net amount', 'net', 'net price', 'purchase price', 'buying price', 'buy price',
            'cost price', 'ticket cost', 'total fare', 'net payable', 'vendor price', 'seller price', 'supplier price',
            'vendor fare', 'vendor amount', 'vendor cost', 'cost amount', 'purchase amount'],
        'seller' => ['seller', 'vendor', 'supplier', 'portal', 'purchased from', 'bought from', 'vendor name', 'supplier name',
            'seller name', 'consolidator'],
        'agent' => ['agent', 'agent name', 'sold to', 'customer', 'client', 'buyer', 'sub agent', 'agency'],
        'sale' => ['agent price', 'sale price', 'selling price', 'sell price', 'sale', 'sales price', 'sold price',
            'agent fare', 'selling fare', 'sale amount'],
        'currency' => ['currency', 'cur'],
        // shown on the ticket card for staff to read; a date here on a round trip is its return day
        'status' => ['status', 'ticket status'],
        'note' => ['remarks', 'remark', 'notes', 'note', 'comments', 'comment'],
    ];

    /** Headings the ERP works out itself or that only repeat another column — never listed as "not used". */
    private const IGNORED = ['profit', 'profit loss', 'loss', 'profit or loss', 'sl', 'sl no', 'serial', 'serial no', 'no',
        'office ref', 'office', 'segment', 'segments', 'source'];

    /**
     * Vague headings: counted only when no clearer column names the same
     * thing — "Date" beside "Travel date" is the sale's date, not the flight's.
     */
    private const WEAK = [
        'date' => 'dep', 'time' => 'dep_time', 'ticket' => 'eticket', 'issue' => 'issue_date', 'fare' => 'base',
        'total' => 'total', 'amount' => 'total', 'total amount' => 'total', 'price' => 'sale', 'source' => 'seller',
    ];

    /** When no heading matches exactly: a word in it decides. First rule wins. */
    private const CONTAINS = [
        ['/\bairline\b.*\bpnr\b|\bpnr\b.*\bairline\b/', 'airline_pnr'],
        ['/\bpnr\b|\bbooking ref|\blocator\b/', 'pnr'],
        ['/\bpassport\b/', 'passport'],
        ['/\breturn\b.*\bflight\b/', 'return_flight'],
        ['/\breturn\b/', 'return_dep'],
        ['/\be ?ticket\b|\bticket (no|number)\b|\btkt\b/', 'eticket'],
        ['/\b(vendor|supplier|seller|purchase|buying|buy)\b.*\b(price|fare|amount|cost)\b/', 'total'],
        ['/\bagent price\b|\bsale\b|\bsell/', 'sale'],
        ['/\bvendor\b|\bsupplier\b|\bseller\b|\bportal\b/', 'seller'],
        ['/\bagent\b|\bsold to\b|\bcustomer\b|\bclient\b/', 'agent'],
        ['/\bpassenger\b|\bpax\b|\btravell?er\b/', 'name'],
        ['/\bissue/', 'issue_date'],
        ['/\bflight (no|number)\b/', 'flight_no'],
        ['/\bdepart.*\btime\b/', 'dep_time'],
        ['/\bdepart|\btravel date\b|\bjourney date\b/', 'dep'],
        ['/\barriv/', 'arr'],
        ['/\bsector\b|\broute\b/', 'route'],
        ['/\btax/', 'tax'],
        ['/\bbase\b/', 'base'],
        ['/\bnet\b|\bcost\b/', 'total'],
        ['/\bclass\b|\bcabin\b/', 'cabin'],
        ['/\bbaggage\b|\bluggage\b/', 'baggage'],
        ['/\bairline\b|\bcarrier\b/', 'carrier'],
    ];

    /**
     * Words staff leave in a cell they had nothing for — the daily sheet's own sample row says
     * "Optional" under Passport No and Remarks (owner, 2026-11-01). Read as an empty cell: a passport
     * "OPTIONAL" matches no holder and so blocked the passenger's match by name.
     */
    private const PLACEHOLDERS = ['optional', 'n a', 'na', 'nil', 'none', 'null'];

    /** Three-letter words in a route that are not airports ("DAC VIA DOH"). */
    private const NOT_AIRPORTS = ['VIA', 'AND', 'THE', 'RTN', 'RET', 'ONE', 'WAY', 'TRP', 'OUT'];

    private ?array $airlineCodes = null;

    /** [col => [row => true]]: cells of the sheet being read that are real Excel dates, not typed text. */
    private array $dateCells = [];

    private bool $lastWasDate = false;

    /** Heading → field, or null when the column is not used. */
    public static function field(string $heading): ?string
    {
        return self::match($heading)[0] ?? null;
    }

    /** @return array{0: string, 1: int}|null field, and how sure: 0 its exact name, 1 a word in it, 2 vague */
    private static function match(string $heading): ?array
    {
        $h = self::norm($heading);
        if ($h === '') {
            return null;
        }
        foreach (self::HEADINGS as $field => $names) {
            if (in_array($h, $names, true)) {
                return [$field, 0];
            }
        }
        if (isset(self::WEAK[$h])) {
            return [self::WEAK[$h], 2];
        }
        foreach (self::CONTAINS as [$re, $field]) {
            if (preg_match($re, $h)) {
                return [$field, 1];
            }
        }

        return null;
    }

    /** "E-ticket No." → "e ticket no" */
    private static function norm(string $heading): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($heading))));
    }

    /** The template as a workbook: ONE sheet, the heading row, columns pre-formatted — nothing else. */
    public static function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Tickets');
        $col = 0;
        foreach (self::TEMPLATE as $heading => [$width, $format]) {
            $c = Coordinate::stringFromColumnIndex(++$col);
            $sheet->setCellValue($c.'1', $heading);
            $sheet->getColumnDimension($c)->setWidth($width);
            $sheet->getStyle($c.'2:'.$c.'1000')->getNumberFormat()->setFormatCode($format);
        }
        $last = Coordinate::stringFromColumnIndex($col);
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');

        return $book;
    }

    /**
     * @return array{ok: bool, message: ?string, label: string, sheet: ?string, rows: int, skipped: int,
     *               truncated: bool, unused: string[], results: array}
     */
    public function read(UploadedFile $file): array
    {
        $name = (string) $file->getClientOriginalName();
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $out = ['ok' => false, 'message' => null, 'label' => $ext === 'csv' ? 'CSV' : 'Excel', 'sheet' => null,
            'rows' => 0, 'skipped' => 0, 'truncated' => false, 'limit' => self::MAX_ROWS, 'unused' => [], 'turned' => [], 'results' => []];

        $type = ['csv' => 'Csv', 'xlsx' => 'Xlsx', 'xls' => 'Xls'][$ext] ?? null;
        if (! $type) {
            $out['message'] = 'Only .csv, .xlsx and .xls files can be imported here.';

            return $out;
        }
        try {
            $reader = IOFactory::createReader($type);
            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
            }
            // only the part a ticket import can use is loaded: a huge sheet costs nothing more
            $reader->setReadFilter(new class(self::HEAD_ROWS + self::MAX_ROWS + 1, self::MAX_COLS) implements IReadFilter
            {
                public function __construct(private int $rows, private int $cols) {}

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row <= $this->rows && Coordinate::columnIndexFromString($columnAddress) <= $this->cols;
                }
            });
            $book = $reader->load($file->getRealPath());
        } catch (\Throwable $e) {
            report($e);
            $out['message'] = 'This file could not be opened as a spreadsheet — save it again as .xlsx or .csv and retry.';

            return $out;
        }

        // the sheet the file was saved on, then the last one back (the newest) — the first with a heading row
        $sheets = array_reverse($book->getAllSheets());
        usort($sheets, fn ($a, $b) => ($b === $book->getActiveSheet()) <=> ($a === $book->getActiveSheet()));
        foreach (array_slice($sheets, 0, 10) as $sheet) {
            $grid = $this->grid($sheet);
            [$headRow, $cols] = $this->headings($grid);
            if ($headRow === null) {
                continue;
            }
            $out['sheet'] = $sheet->getTitle();
            $out['ok'] = true;
            $out['turned'] = [];
            foreach ($this->dayFirst($grid, $headRow) as $c => $was) {
                $out['turned'][] = ($grid[$headRow][$c] ?? 'A date column').': Excel had stored '.$was;
            }
            foreach ($grid[$headRow] as $c => $heading) {
                if ($heading !== '' && ! in_array($c, $cols, true) && ! in_array(self::norm($heading), self::IGNORED, true)) {
                    $out['unused'][] = $heading;
                }
            }
            $last = array_key_last($grid) ?? $headRow;
            $out['truncated'] = $last > $headRow + self::MAX_ROWS;
            for ($r = $headRow + 1; $r <= min($last, $headRow + self::MAX_ROWS); $r++) {
                $v = [];
                foreach ($cols as $field => $c) {
                    $cell = $grid[$r][$c] ?? '';
                    $v[$field] = in_array(self::norm($cell), self::PLACEHOLDERS, true) || trim($cell, " -\u{2013}\u{2014}") === '' ? '' : $cell;
                }
                if (! array_filter($v, fn ($x) => $x !== '')) {
                    continue;   // an empty row
                }
                $res = $this->row($v, $name, $out['label'].' · row '.$r);
                if ($res === null) {
                    $out['skipped']++;

                    continue;
                }
                $res['sheet']['row'] = $r;
                $out['results'][] = $res;
            }
            $out['rows'] = count($out['results']);
            break;
        }
        $book->disconnectWorksheets();
        if (! $out['ok']) {
            $out['message'] = 'No heading row found — the first row must name the columns (PNR, Passenger name, Route …). The daily sheet template has them.';
        }

        return $out;
    }

    /** The sheet as text: [row => [col => string]], rows numbered as Excel shows them. */
    private function grid(Worksheet $sheet): array
    {
        $rows = min($sheet->getHighestDataRow(), self::HEAD_ROWS + self::MAX_ROWS + 1);
        $cols = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_COLS);
        $grid = [];
        $this->dateCells = [];
        for ($r = 1; $r <= $rows; $r++) {
            $line = [];
            for ($c = 1; $c <= $cols; $c++) {
                $this->lastWasDate = false;
                $line[$c] = $sheet->cellExists([$c, $r]) ? $this->text($sheet->getCell([$c, $r])) : '';
                if ($this->lastWasDate) {
                    $this->dateCells[$c][$r] = true;
                }
            }
            if (array_filter($line, fn ($x) => $x !== '')) {
                $grid[$r] = $line;
            }
        }

        return $grid;
    }

    private function text(Cell $cell): string
    {
        $v = $cell->getValue();
        if ($cell->isFormula()) {
            $v = $cell->getOldCalculatedValue();   // what Excel last showed — never calculated here
        }
        if ($v instanceof RichText) {
            $v = $v->getPlainText();
        }
        if ($v === null || $v === '') {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'TRUE' : 'FALSE';
        }
        if (is_numeric($v) && ! is_string($v) && ExcelDate::isDateTime($cell)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $v);
                if ((float) $v < 1) {
                    return $dt->format('H:i');   // a time on its own
                }
                $this->lastWasDate = true;

                return $dt->format('H:i') === '00:00' ? $dt->format('Y-m-d') : $dt->format('Y-m-d H:i');
            } catch (\Throwable) {
                // not a real date after all: the number as it is
            }
        }
        if (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
            return sprintf('%.0f', $v);   // 13-digit e-tickets stay whole, never 1.57E+12
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    /**
     * Day-first dates typed into a month-first Excel: the owner's demo sheet (2026-10-19) has Issue
     * Dates 4/10 … 7/10/2026 — meant 4–7 October — stored as 10 April … 10 July, because that Excel
     * reads the month first ("13/10/2026" it cannot read stays text, and is read right). A column of
     * real date cells whose every day could be a month is turned round when that puts its dates both
     * nearer today and closer together: a day's tickets are issued around today, not months apart.
     * Typed text dates are always read day-first and never touched here.
     *
     * @return array<int, string> column → what Excel had stored ("10 Apr – 10 Jul 2026")
     */
    private function dayFirst(array &$grid, int $headRow): array
    {
        $today = strtotime(date('Y-m-d'));
        $turned = [];
        foreach ($this->dateCells as $c => $rows) {
            $as = $swap = $parts = [];
            foreach (array_keys($rows) as $r) {
                if ($r <= $headRow || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})(.*)$/', $grid[$r][$c] ?? '', $m)) {
                    continue;
                }
                [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
                if ($d > 12) {
                    continue 2;   // a day that cannot be a month: this column was read as typed
                }
                $as[] = mktime(0, 0, 0, $mo, $d, $y);
                $swap[] = mktime(0, 0, 0, $d, $mo, $y);
                $parts[$r] = [$y, $mo, $d, $m[4]];
            }
            if (count(array_unique($as)) < 2 || $as === $swap) {
                continue;   // one date is no evidence either way
            }
            $near = fn (array $ts) => array_sum(array_map(fn ($t) => abs($t - $today), $ts)) / count($ts);
            $spread = fn (array $ts) => max($ts) - min($ts);
            if ($near($swap) < $near($as) && $spread($swap) * 2 < $spread($as)) {
                foreach ($parts as $r => [$y, $mo, $d, $rest]) {
                    $grid[$r][$c] = sprintf('%04d-%02d-%02d', $y, $d, $mo).$rest;
                }
                $show = fn ($t) => date('j M Y', $t);
                $turned[$c] = (min($as) === max($as) ? $show(min($as)) : $show(min($as)).' – '.$show(max($as)))
                    .', read day-first as '.(min($swap) === max($swap) ? $show(min($swap)) : $show(min($swap)).' – '.$show(max($swap)));
            }
        }

        return $turned;
    }

    /**
     * The heading row: the one among the first rows that names the most known
     * columns — at least two, one of them a passenger, PNR, e-ticket or passport.
     *
     * @return array{0: ?int, 1: array<string, int>} row number, field → column
     */
    private function headings(array $grid): array
    {
        $best = [null, []];
        foreach (array_slice($grid, 0, self::HEAD_ROWS, true) as $r => $line) {
            // surest heading first, then left to right; a column gives one field
            $cands = [];
            foreach ($line as $c => $heading) {
                if ($m = self::match($heading)) {
                    $cands[] = [$m[1], $c, $m[0]];
                }
            }
            usort($cands, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $cols = [];
            foreach ($cands as [, $c, $f]) {
                if (! isset($cols[$f]) && ! in_array($c, $cols, true)) {
                    $cols[$f] = $c;
                }
            }
            $key = array_intersect_key($cols, array_flip(['name', 'surname', 'given', 'pnr', 'eticket', 'passport']));
            if (count($cols) >= 2 && $key && count($cols) > count($best[1])) {
                $best = [$r, $cols];
            }
        }

        return $best;
    }

    /** One row → one ticket in the PDF readers' shape, or null for a row that is not a ticket. */
    private function row(array $v, string $fileName, string $label): ?array
    {
        $warn = [];
        $raw = trim($v['name'] ?? '') !== '' ? trim($v['name']) : trim(($v['given'] ?? '').' '.($v['surname'] ?? ''));
        if (preg_match('/^(grand |sub ?)?totals?\b/i', $raw)) {
            return null;
        }
        $pnr = strtoupper(preg_replace('/\s+/', '', $v['pnr'] ?? ''));
        $passport = strtoupper(preg_replace('/\s+/', '', $v['passport'] ?? ''));
        $eticket = preg_replace('/\s+/', '', $v['eticket'] ?? '');
        // Excel drops a ticket number's leading zero (Gulf Air 072… became 724883925053): back to 13 digits
        if (preg_match('/^\d{12}$/', $eticket)) {
            $eticket = '0'.$eticket;
        }
        $segs = $this->segments($v, $warn);
        if ($raw === '' && $pnr === '' && $passport === '' && $eticket === '' && ! $segs) {
            return null;   // a note or a blank line between groups
        }

        $res = TicketPdfImporter::blank($fileName);
        $res['layout'] = 'sheet';
        $res['layout_label'] = $label;
        $res['pnr'] = $pnr !== '' ? mb_substr($pnr, 0, 30) : null;
        $res['airline_pnr'] = ($a = strtoupper(preg_replace('/\s+/', '', $v['airline_pnr'] ?? ''))) !== '' && $a !== $pnr ? $a : null;
        $res['currency'] = ($v['currency'] ?? '') !== '' ? strtoupper($v['currency']) : null;
        $res['issue_date'] = $this->day($v['issue_date'] ?? '', 'Issue date', $warn);
        if ($res['issue_date'] && $res['issue_date'] > date('Y-m-d')) {
            $warn[] = 'Issue date '.date('d.m.Y', strtotime($res['issue_date'])).' is after today — check it.';
        }
        $this->statusNotes($v, $segs, $warn);
        $res['segments'] = $segs;
        $res['carrier'] = $this->carrierCode($v['carrier'] ?? '') ?? ($segs[0]['carrier'] ?? null);

        if ($raw !== '') {
            [$name, $title] = $this->person($raw);
            $type = strtoupper($v['pax_type'] ?? '');
            $res['passengers'][] = [
                'name' => $name,
                'raw_name' => $raw,
                'title' => ($v['title'] ?? '') !== '' ? strtoupper(rtrim($v['title'], '.')) : $title,
                'passport_no' => $passport !== '' ? $passport : null,
                'dob' => $this->day($v['dob'] ?? '', 'Date of birth', $warn),
                'eticket' => $eticket !== '' ? $eticket : null,
                'pax_type' => str_starts_with($type, 'C') ? 'child' : (str_starts_with($type, 'I') ? 'infant' : 'adult'),
                'fare' => $this->fare($v, $warn),
            ];
        } else {
            $warn[] = 'No passenger in this row — choose the passenger on the card.';
        }
        if (! $segs) {
            $warn[] = 'No route in this row (Route, or From and To) — choose the route on the card.';
        }
        // what the card's Sold to / Seller / Agent price take — matched by name in the browser
        $res['sheet'] = [
            'seller' => ($v['seller'] ?? '') !== '' ? $v['seller'] : null,
            'agent' => ($v['agent'] ?? '') !== '' ? $v['agent'] : null,
            'sale' => $this->money($v['sale'] ?? '', 'Agent price', $warn),
        ];
        $res['warnings'] = $warn;
        $res['status'] = $res['passengers'] && $segs ? 'read' : 'partial';

        return $res;
    }

    /**
     * Status / Remarks are read by staff, not by us: each is a note on the card. One exception — the
     * daily sheet writes a round trip's return day in Status ("01.03.2027" beside ECN-DAC-ECN), so a
     * date there fills the empty return, with a note to check it. Remarks repeating Status is said once.
     */
    private function statusNotes(array $v, array &$segs, array &$warn): void
    {
        $ret = array_key_first(array_filter($segs, fn ($s) => $s['direction'] === 'return'));
        $status = trim($v['status'] ?? '');
        foreach (['status' => 'Status', 'note' => 'Remarks'] as $f => $label) {
            $text = trim($v[$f] ?? '');
            if ($text === '' || ($f === 'note' && strcasecmp($text, $status) === 0)) {
                continue;
            }
            if ($ret !== null && empty($segs[$ret]['dep']) && ($day = $this->parseDay($text))) {
                $segs[$ret]['dep'] = $day.' 00:00';
                $warn[] = 'Return date taken from '.$label.' ('.$text.') — check it.';

                continue;
            }
            if ($f === 'status' && in_array(strtolower($text), ['purchased', 'ticket', 'issued', 'ok', 'done', 'confirmed'], true)) {
                continue;
            }
            $warn[] = $label.' in the sheet: '.mb_substr($text, 0, 120);
        }
    }

    /** Route (or From / Via / To), flights and dates → segments; a return date or DAC-ECN-DAC adds the way back. */
    private function segments(array $v, array &$warn): array
    {
        $codes = $this->airports($v['route'] ?? '');
        if (count($codes) < 2) {
            $from = $this->airports($v['from'] ?? '');
            $to = $this->airports($v['to'] ?? '');
            $codes = $from && $to ? [$from[0], ...$this->airports($v['via'] ?? ''), end($to)] : [];
        }
        if (count($codes) < 2) {
            if (trim(($v['route'] ?? '').($v['from'] ?? '').($v['to'] ?? '')) !== '') {
                $warn[] = 'The route "'.trim(($v['route'] ?? '') ?: ($v['from'] ?? '').' → '.($v['to'] ?? '')).'" could not be read — write it with airport codes, e.g. DAC-IST.';
            }

            return [];
        }
        $flights = $this->flights($v['flight_no'] ?? '');
        $carrier = $this->carrierCode($v['carrier'] ?? '');
        $dep = $this->when($v['dep'] ?? '', $v['dep_time'] ?? '', 'Departure', $warn);
        $arr = $this->when($v['arr'] ?? '', '', 'Arrival', $warn, $dep);
        $cabin = $this->cabin($v['cabin'] ?? '');

        $segs = [];
        for ($i = 0, $n = count($codes) - 1; $i < $n; $i++) {
            $f = $flights[$i] ?? null;
            $segs[] = $this->segment($f[0] ?? $carrier, $f[1] ?? null, $codes[$i], $codes[$i + 1], $cabin, $v);
        }
        // DAC-ECN-DAC: the second half is the way back
        $turn = count($segs) > 1 && $codes[0] === end($codes) ? intdiv(count($segs), 2) : count($segs);
        foreach ($segs as $k => $s) {
            $segs[$k]['direction'] = $k >= $turn ? 'return' : 'out';
        }
        $segs[0]['dep'] = $dep;
        $segs[$turn - 1]['arr'] = $arr;

        $retDep = $this->when($v['return_dep'] ?? '', '', 'Return', $warn);
        $retFlights = $this->flights($v['return_flight'] ?? '');
        if ($turn < count($segs)) {
            $segs[$turn]['dep'] = $retDep;
            if ($retFlights) {
                [$segs[$turn]['carrier'], $segs[$turn]['flight_no']] = $retFlights[0];
            }
        } elseif ($retDep || $retFlights) {
            $segs[] = ['direction' => 'return', 'dep' => $retDep]
                + $this->segment($retFlights[0][0] ?? $carrier, $retFlights[0][1] ?? null, end($codes), $codes[0], $cabin, $v);
        }

        return $segs;
    }

    private function segment(?string $carrier, ?string $flight, string $from, string $to, ?string $cabin, array $v): array
    {
        return [
            'carrier' => $carrier, 'flight_no' => $flight,
            'from' => $from, 'to' => $to, 'from_name' => null, 'to_name' => null,
            'dep' => null, 'arr' => null,
            'cabin' => $cabin,
            'rbd' => ($v['rbd'] ?? '') !== '' ? strtoupper(mb_substr($v['rbd'], 0, 5)) : null,
            'baggage' => ($v['baggage'] ?? '') !== '' ? mb_substr($v['baggage'], 0, 40) : null,
            'status' => null, 'direction' => 'out',
        ];
    }

    /** "DAC-IST-ECN", "Dhaka (DAC) → Istanbul (IST)", "Dhaka" → ['DAC', …] in order. */
    private function airports(string $s): array
    {
        $s = trim($s);
        if ($s === '') {
            return [];
        }
        if (preg_match_all('/\(([A-Za-z]{3})\)/', $s, $m) && $m[1]) {
            return array_map('strtoupper', $m[1]);
        }
        // codes only, any case: "dac-ist", "DAC / BAH / IST"
        if (preg_match('/^[A-Za-z]{3}(?:[^A-Za-z0-9]+[A-Za-z]{3})+$/u', $s)) {
            $codes = array_values(array_diff(array_map('strtoupper', preg_split('/[^A-Za-z]+/', $s, -1, PREG_SPLIT_NO_EMPTY)), self::NOT_AIRPORTS));
            if (count($codes) >= 2) {
                return $codes;
            }
        }
        if (preg_match_all('/(?<![A-Za-z])[A-Z]{3}(?![A-Za-z])/', $s, $m)) {
            $codes = array_values(array_diff($m[0], self::NOT_AIRPORTS));
            if ($codes) {
                return $codes;
            }
        }
        if (preg_match('/^[A-Za-z]{3}$/', $s)) {
            return [strtoupper($s)];
        }

        return Cities::inOrder($s);
    }

    /** "GF 249 / GF15, TK 1971" → [['GF', 'GF 249'], …] */
    private function flights(string $s): array
    {
        $out = [];
        foreach (preg_split('/[\/,;+&|]+/', strtoupper($s)) as $part) {
            if (preg_match('/\b([A-Z][A-Z0-9]|[0-9][A-Z])\s*-?\s*(\d{1,4}[A-Z]?)\b/', $part, $m)) {
                $out[] = [$m[1], $m[1].' '.ltrim($m[2], '0')];
            }
        }

        return $out;
    }

    /** "GF", "Gulf Air", "Gulf Air (GF)" → "GF". */
    private function carrierCode(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/\(([A-Z0-9]{2})\)/i', $s, $m) || preg_match('/^([A-Z0-9]{2})$/i', $s, $m)) {
            return strtoupper($m[1]);
        }
        if ($this->airlineCodes === null) {
            $this->airlineCodes = [];
            foreach (Directory::AIRLINES as $code => $name) {
                $this->airlineCodes[self::key($name)] = $code;
            }
            try {
                foreach (Airline::query()->get(['name']) as $a) {
                    if (preg_match('/^(.*?)\s*\(([A-Z0-9]{2})\)\s*$/i', (string) $a->name, $m)) {
                        $this->airlineCodes[self::key($m[1])] = strtoupper($m[2]);
                    }
                }
            } catch (\Throwable) {
                // Ticketing's airlines unreadable: the Directory alone
            }
        }

        return $this->airlineCodes[self::key($s)] ?? null;
    }

    private static function key(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($s));
    }

    private function cabin(string $s): ?string
    {
        $s = strtoupper($s);

        return match (true) {
            $s === '' => null,
            str_contains($s, 'BUSINESS') || $s === 'C' || $s === 'J' => 'business',
            str_contains($s, 'FIRST') || $s === 'F' => 'first',
            str_contains($s, 'ECO') || $s === 'Y' => 'economy',
            default => null,
        };
    }

    private function fare(array $v, array &$warn): ?array
    {
        $base = $this->money($v['base'] ?? '', 'Base fare', $warn);
        $tax = $this->money($v['tax'] ?? '', 'Tax', $warn);
        $total = $this->money($v['total'] ?? '', 'Net cost', $warn);
        if ($base === null && $tax === null && $total === null) {
            return null;
        }

        return ['base' => $base, 'tax' => $tax, 'total' => $total,
            'tax_breakdown' => ($v['tax_breakdown'] ?? '') !== '' ? mb_substr($v['tax_breakdown'], 0, 255) : null];
    }

    /** "BDT 1,23,456.50" → 123456.5; text that is not a number is a note, never a guess. */
    private function money(string $s, string $what, array &$warn): ?float
    {
        $s = trim($s);
        if ($s === '' || $s === '-') {
            return null;
        }
        $n = str_replace(',', '', preg_replace('/^[^\d\-]+|[^\d.]+$/u', '', $s));
        if (! is_numeric($n)) {
            $warn[] = $what.' "'.$s.'" is not a number — type it on the card.';

            return null;
        }

        return round((float) $n, 2);
    }

    /** A date (with a time when it has one) → "Y-m-d H:i", as the PDF readers give it. */
    private function when(string $date, string $time, string $what, array &$warn, ?string $sameDayAs = null): ?string
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' && $time === '') {
            return null;
        }
        $day = $this->parseDay($date);
        $hm = $this->parseTime($time !== '' ? $time : $date);
        // "14:30" alone in Arrival: the day of the departure
        if (! $day && $hm && $sameDayAs && ! preg_match('/\d{1,2}[\/.\-]\d{1,2}|[A-Za-z]{3}/', $date)) {
            $day = substr($sameDayAs, 0, 10);
        }
        if (! $day) {
            $warn[] = $what.' "'.trim($date.' '.$time).'" could not be read as a date — enter it on the card.';

            return null;
        }

        return $day.' '.($hm ?? '00:00');
    }

    private function day(string $s, string $what, array &$warn): ?string
    {
        if (trim($s) === '') {
            return null;
        }
        $day = $this->parseDay($s);
        if (! $day) {
            $warn[] = $what.' "'.trim($s).'" could not be read as a date — enter it on the card.';
        }

        return $day;
    }

    /** 2026-10-20 · 20/10/2026 · 20-10-26 · 20.10.2026 · 20 Oct 2026 · 20-Oct-26 · Oct 20, 2026 → Y-m-d. Day first, as here. */
    private function parseDay(string $s): ?string
    {
        $s = trim($s);
        $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
            'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
        $y = $m = $d = null;
        if (preg_match('/^(\d{4})[\-\/.](\d{1,2})[\-\/.](\d{1,2})/', $s, $x)) {
            [$y, $m, $d] = [(int) $x[1], (int) $x[2], (int) $x[3]];
        } elseif (preg_match('/^(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{2}|\d{4})(?!\d)/', $s, $x)) {
            [$d, $m, $y] = [(int) $x[1], (int) $x[2], (int) $x[3]];
            if ($m > 12 && $d <= 12) {
                [$d, $m] = [$m, $d];   // 10/20/2026: month first after all
            }
        } elseif (preg_match('/^(\d{1,2})\s*[\-\/ ]?\s*([A-Za-z]{3})[A-Za-z]*\.?\s*[\-\/ ,]?\s*(\d{4}|\d{2})(?!\d)/', $s, $x)) {
            [$d, $m, $y] = [(int) $x[1], $months[strtoupper($x[2])] ?? null, (int) $x[3]];
        } elseif (preg_match('/^([A-Za-z]{3})[A-Za-z]*\.?\s+(\d{1,2}),?\s+(\d{4})/', $s, $x)) {
            [$m, $d, $y] = [$months[strtoupper($x[1])] ?? null, (int) $x[2], (int) $x[3]];
        }
        if (! $m || ! $d || ! $y) {
            return null;
        }
        if ($y < 100) {
            $y += 2000;
        }

        return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    /** "09:55", "9:55 PM", "21:55:00" → "HH:MM". */
    private function parseTime(string $s): ?string
    {
        if (! preg_match('/(?<!\d)(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?/', $s, $x)) {
            return null;
        }
        $h = (int) $x[1];
        $ampm = strtoupper($x[3] ?? '');
        if ($ampm === 'PM' && $h < 12) {
            $h += 12;
        } elseif ($ampm === 'AM' && $h === 12) {
            $h = 0;
        }

        return $h < 24 && (int) $x[2] < 60 ? sprintf('%02d:%02d', $h, (int) $x[2]) : null;
    }

    /** "TALUKDER/MD AL AMIN MR" → ['MD AL AMIN TALUKDER', 'MR']; "Mr. Md Jone" → ['MD JONE', 'MR'] (as Reader::person). */
    private function person(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        $title = null;
        $titles = 'MR|MRS|MS|MISS|MSTR|MASTER|DR|CHD|INF';
        if (preg_match('/^('.$titles.')\.?\s+/i', $raw, $m)) {
            $title = strtoupper($m[1]);
            $raw = substr($raw, strlen($m[0]));
        }
        if (preg_match('/\s+('.$titles.')\.?$/i', $raw, $m)) {
            $title = strtoupper($m[1]);
            $raw = substr($raw, 0, -strlen($m[0]));
        }
        if (str_contains($raw, '/')) {
            [$surname, $given] = array_map('trim', explode('/', $raw, 2));
            $raw = trim($given.' '.$surname);
        }

        return [strtoupper(trim($raw)), $title];
    }
}
