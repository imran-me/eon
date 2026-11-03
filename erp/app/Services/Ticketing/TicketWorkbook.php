<?php

namespace App\Services\Ticketing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Ticketing workbook (owner, 2026-10-11): everything Ticketing records,
 * as an Excel file people can explore — built from the database the moment
 * it is opened, so every entry and every edit is already in it.
 *
 * Tabs: Dashboard (totals, month chart, who owes / is owed, links to every
 * tab) · Ticket Orders (one row per ticket) · Bookings (holds, drafts) ·
 * Changes (reissue / reschedule / refund / void) · Sellers · Agents ·
 * Monthly P&L. Figures come from TicketAccountsController::figures(), the
 * same numbers as the Ticket Accounts page.
 *
 * Reads only.
 */
final class TicketWorkbook
{
    private const BRAND = '4F46E5';
    private const MONEY = '#,##0.00;[Red]-#,##0.00;"–"';
    private const DATE = 'dd mmm yyyy';
    private const STAMP = 'dd mmm yyyy hh:mm';

    /**
     * Every tab as plain data, recorded while the sheets are built — what the
     * in-browser viewer draws (title, headers, rows, and how to show each column).
     *
     * @var array<int, array{name: string, title: string, head: array, rows: array, kinds: array, totals: array}>
     */
    public array $views = [];

    public function build(array $f, ?int $companyId): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Epal ERP')->setTitle('Ticketing workbook')
            ->setDescription('Built from the ERP at ' . now()->format('d M Y H:i'));
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $dash = $book->getActiveSheet()->setTitle('Dashboard');
        $orders = $this->orders($book->createSheet()->setTitle('Ticket Orders'), $companyId);
        $holds = $this->bookings($book->createSheet()->setTitle('Bookings'), $companyId);
        $this->changes($book->createSheet()->setTitle('Changes'), $f['changes']);
        $this->sellers($book->createSheet()->setTitle('Sellers'), $f['vendorSheet']);
        $this->agents($book->createSheet()->setTitle('Agents'), $f['agentSheet']);
        $months = $this->monthly($book->createSheet()->setTitle('Monthly P&L'), $f['monthly']);
        $this->dashboard($dash, $f, $orders, $holds, $months);

        $book->setActiveSheetIndex(0);
        return $book;
    }

    // ── tabs ─────────────────────────────────────────────────────────────

    private function dashboard(Worksheet $s, array $f, int $orders, int $holds, int $months): void
    {
        $o = $f['overview'];
        $s->setShowGridlines(false);
        foreach (['A' => 3, 'B' => 26, 'C' => 18, 'D' => 4, 'E' => 26, 'F' => 18, 'G' => 4, 'H' => 26, 'I' => 18] as $c => $w) {
            $s->getColumnDimension($c)->setWidth($w);
        }
        $s->setCellValue('B2', 'Ticketing — workbook');
        $s->getStyle('B2')->getFont()->setSize(20)->setBold(true)->getColor()->setRGB('1E293B');
        $s->setCellValue('B3', 'Built from the ERP on ' . now()->format('d M Y, H:i') . ' — every entry and edit up to this moment. Open again from Ticket Accounts for the latest.');
        $s->getStyle('B3')->getFont()->setItalic(true)->getColor()->setRGB('64748B');
        if ($f['from'] || $f['to']) {
            $s->setCellValue('B4', 'Period: ' . ($f['from']?->format('d M Y') ?? 'start') . ' – ' . ($f['to']?->format('d M Y') ?? 'today'));
        }

        // tiles: label over a big figure, three per row
        $tiles = [
            ['Tickets issued', $o['tickets'], '0', '4F46E5'], ['Ticket cost', $o['cost'], self::MONEY, 'B91C1C'], ['Sales revenue', $o['revenue'], self::MONEY, '15803D'],
            ['Profit on sold tickets', $o['sold_profit'], self::MONEY, '15803D'], ['Margin', $o['margin'] === null ? null : $o['margin'] / 100, '0.0%', '0F766E'], ['Cancelled tickets', $o['cancelled'], '0', 'B45309'],
            ['Owed to sellers', $o['supplier_due'], self::MONEY, 'B91C1C'], ['Agents owe us', $o['agent_due'], self::MONEY, '1D4ED8'], ['Paid to sellers', $o['supplier_paid'], self::MONEY, '475569'],
        ];
        foreach ($tiles as $i => [$label, $value, $fmt, $color]) {
            $col = ['B', 'E', 'H'][$i % 3];
            $row = 6 + intdiv($i, 3) * 3;
            $val = $this->next($col);
            $s->mergeCells("{$col}{$row}:{$val}{$row}")->mergeCells($col . ($row + 1) . ':' . $val . ($row + 1));
            $s->setCellValue("{$col}{$row}", strtoupper($label));
            $s->setCellValue($col . ($row + 1), $value ?? '—');
            $s->getStyle("{$col}{$row}:{$val}" . ($row + 1))->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                'borders' => ['left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => $color]]],
            ]);
            $s->getStyle("{$col}{$row}")->getFont()->setSize(8)->setBold(true)->getColor()->setRGB('64748B');
            $s->getStyle($col . ($row + 1))->getFont()->setSize(16)->setBold(true)->getColor()->setRGB($color);
            $s->getStyle($col . ($row + 1))->getNumberFormat()->setFormatCode($fmt);
            $s->getStyle($col . ($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $s->getRowDimension($row + 1)->setRowHeight(26);
        }

        // go to: one link per tab
        $r = 16;
        $s->setCellValue("B{$r}", 'Go to');
        $this->heading($s, "B{$r}");
        $links = [['Ticket Orders', "{$orders} tickets — PNR, passenger, route, seller, cost, agent, profit"], ['Bookings', "{$holds} holds and drafts"],
            ['Changes', $f['changes']->count() . ' reissues, reschedules, refunds and voids'], ['Sellers', 'what each seller billed, was paid and is owed'],
            ['Agents', 'what each agent was charged, paid and owes'], ['Monthly P&L', "{$months} months of cost, revenue and profit"]];
        foreach ($links as $i => [$tab, $what]) {
            $row = $r + 1 + $i;
            $s->setCellValue("B{$row}", '→ ' . $tab);
            $s->getCell("B{$row}")->getHyperlink()->setUrl("sheet://'{$tab}'!A1");
            $s->getStyle("B{$row}")->getFont()->setUnderline(true)->setBold(true)->getColor()->setRGB(self::BRAND);
            $s->setCellValue("C{$row}", $what);
            $s->getStyle("C{$row}")->getFont()->getColor()->setRGB('475569');
        }

        // who owes and who is owed — the five biggest of each
        $r = 24;
        $s->setCellValue("B{$r}", 'Biggest seller dues');
        $s->setCellValue("E{$r}", 'Biggest agent dues');
        $this->heading($s, "B{$r}");
        $this->heading($s, "E{$r}");
        foreach ($f['vendorSheet']->where('due', '>', 0)->take(5)->values() as $i => $v) {
            $s->setCellValue('B' . ($r + 1 + $i), $v['name'] . ($v['portal'] ? ' (portal)' : ''))->setCellValue('C' . ($r + 1 + $i), $v['due']);
        }
        foreach ($f['agentSheet']->where('due', '>', 0)->take(5)->values() as $i => $a) {
            $s->setCellValue('E' . ($r + 1 + $i), $a['name'])->setCellValue('F' . ($r + 1 + $i), $a['due']);
        }
        $s->getStyle('C' . ($r + 1) . ':C' . ($r + 5))->getNumberFormat()->setFormatCode(self::MONEY);
        $s->getStyle('F' . ($r + 1) . ':F' . ($r + 5))->getNumberFormat()->setFormatCode(self::MONEY);

        // the month chart: revenue, cost and profit from the Monthly P&L tab, oldest first
        if ($months > 0) {
            $last = $months + 1;
            $cats = [new DataSeriesValues('String', "'Monthly P&L'!\$A\$2:\$A\${$last}", null, $months)];
            $names = [];
            $vals = [];
            foreach (['G' => 'Revenue', 'H' => 'Sold cost', 'I' => 'Profit'] as $c => $n) {
                $names[] = new DataSeriesValues('String', "'Monthly P&L'!\${$c}\$1", null, 1);
                $vals[] = new DataSeriesValues('Number', "'Monthly P&L'!\${$c}\$2:\${$c}\${$last}", null, $months);
            }
            $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, 2), $names, $cats, $vals);
            $series->setPlotDirection(DataSeries::DIRECTION_COL);
            $chart = new Chart('months', new Title('Revenue, cost and profit by month'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]));
            $chart->setTopLeftPosition('B32')->setBottomRightPosition('I50');
            $s->addChart($chart);
        }
    }

    /** One row per ticket bought — everything Ticket Orders shows, plus money. */
    private function orders(Worksheet $s, ?int $companyId): int
    {
        $has = fn ($c) => Schema::hasColumn('ticket_purchases', $c);
        $q = DB::table('ticket_purchases as p')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'p.passport_holder_id')
            ->leftJoin('tickets as t', 't.id', '=', 'p.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'p.portal_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'p.created_by')
            ->whereNull('p.deleted_at')
            ->when($companyId, fn ($x) => $x->where('p.company_id', $companyId))
            ->orderByDesc('p.created_at')->orderByDesc('p.id')
            ->select('p.id', 'p.ticket_no', 'p.purchase_date', 'p.amount', 'p.paid_amount', 'p.due_amount', 'p.status', 'p.created_at',
                'ph.name as passenger', 'ph.passport_no', 't.title as route', DB::raw('COALESCE(v.name, po.name) as seller'), 'cu.name as entered_by')
            ->addSelect(DB::raw("(SELECT SUM(si.price) FROM ticket_sale_items si JOIN ticket_sales s ON s.id = si.ticket_sale_id WHERE si.ticket_purchase_id = p.id AND s.deleted_at IS NULL AND s.status <> 'cancelled') as sale_price"))
            ->addSelect(DB::raw("(SELECT u.name FROM ticket_sale_items si JOIN ticket_sales s ON s.id = si.ticket_sale_id JOIN users u ON u.id = s.client_id WHERE si.ticket_purchase_id = p.id AND s.deleted_at IS NULL AND s.status <> 'cancelled' ORDER BY si.id DESC LIMIT 1) as agent"))
            ->addSelect(DB::raw("(SELECT al.name FROM airlines al WHERE al.id = t.airline_id) as airline"));
        foreach (['name_ticket_no', 'flight_no', 'departure_at', 'refund_amount', 'cancel_note', 'trip_type', 'ticket_class'] as $c) {
            $q->addSelect($has($c) ? "p.{$c}" : DB::raw("NULL as {$c}"));
        }
        if (Schema::hasTable('contract_flight_passenger_tickets')) {
            $q->addSelect(DB::raw('(SELECT f.flight_number FROM contract_flight_passenger_tickets x JOIN contract_flight_passengers cp ON cp.id = x.contract_flight_passenger_id LEFT JOIN contract_flights f ON f.id = cp.contract_flight_id WHERE x.ticket_purchase_id = p.id LIMIT 1) as cf_flight'));
        } else {
            $q->addSelect(DB::raw('NULL as cf_flight'));
        }
        $rows = $q->get();

        $head = ['Entered', 'PNR', 'E-ticket', 'Passenger', 'Passport', 'Route', 'Airline', 'Flight', 'Travel date', 'Class', 'Status',
            'Seller', 'Cost', 'Paid to seller', 'Due to seller', 'Refund', 'Agent / flight', 'Agent price', 'Profit', 'Entered by', 'Note'];
        $data = $rows->map(function ($r) {
            $cancelled = $r->status === 'cancelled';
            $cost = (float) $r->amount;
            $refund = $cancelled && $r->refund_amount !== null ? (float) $r->refund_amount : null;
            $price = $r->sale_price !== null ? (float) $r->sale_price : null;
            return [
                $this->xlDate($r->created_at), $r->ticket_no, $r->name_ticket_no, $r->passenger, $r->passport_no, $r->route, $r->airline,
                $r->flight_no, $this->xlDate($r->departure_at), $r->ticket_class ? ucfirst($r->ticket_class) : null,
                $cancelled ? 'Cancelled' : ($r->status === 'confirm' ? 'Purchased' : ucfirst((string) $r->status)),
                $r->seller, $cost, (float) $r->paid_amount, $cancelled ? 0 : (float) $r->due_amount, $refund,
                $r->cf_flight ? 'Flight ' . $r->cf_flight : $r->agent, $price,
                $price !== null && ! $cancelled ? $price - $cost : null, $r->entered_by, $r->cancel_note,
            ];
        })->all();

        $this->table($s, 'Ticket Orders — every ticket bought, newest first', $head, $data,
            ['text' => ['B', 'C', 'E', 'H'], 'money' => ['M', 'N', 'O', 'P', 'R', 'S'], 'dates' => ['A' => self::STAMP, 'I' => self::STAMP], 'wide' => ['D' => 26, 'F' => 22, 'G' => 20, 'H' => 22, 'L' => 22, 'Q' => 22, 'U' => 34]]);
        // colour the status and the profit so they read at a glance
        $n = count($data) + 3;
        $this->colourText($s, "K4:K{$n}", ['Cancelled' => 'B91C1C', 'Purchased' => '15803D']);
        $this->signColour($s, "S4:S{$n}");
        $this->totals($s, $n, ['M', 'N', 'O', 'P', 'R', 'S']);
        return count($data);
    }

    private function bookings(Worksheet $s, ?int $companyId): int
    {
        if (! Schema::hasTable('ticket_bookings')) {
            $s->setCellValue('A1', 'Bookings are not installed on this server.');
            return 0;
        }
        $rows = DB::table('ticket_bookings as b')
            ->leftJoin('passport_holders as ph', 'ph.id', '=', 'b.passport_holder_id')
            ->leftJoin('tickets as t', 't.id', '=', 'b.ticket_id')
            ->leftJoin('users as v', 'v.id', '=', 'b.vendor_id')
            ->leftJoin('portals as po', 'po.id', '=', 'b.portal_id')
            ->whereNull('b.deleted_at')->whereNull('b.confirmed_purchase_id')
            ->when($companyId, fn ($x) => $x->where(fn ($y) => $y->where('b.company_id', $companyId)->orWhereNull('b.company_id')))
            ->orderByDesc('b.created_at')
            ->get(['b.created_at', 'b.booking_reference', 'b.pnr', 'ph.name as passenger', 'ph.passport_no', 't.title as route',
                'b.status', 'b.expires_at', 'b.quoted_amount', DB::raw('COALESCE(v.name, po.name) as seller')]);
        $data = $rows->map(fn ($r) => [$this->xlDate($r->created_at), $r->booking_reference, $r->pnr, $r->passenger, $r->passport_no, $r->route,
            ucfirst((string) $r->status), $this->xlDate($r->expires_at), $r->quoted_amount !== null ? (float) $r->quoted_amount : null, $r->seller])->all();
        $this->table($s, 'Bookings — holds, drafts and pending tickets not yet purchased', ['Entered', 'Reference', 'PNR', 'Passenger', 'Passport', 'Route', 'Status', 'Hold expires', 'Quoted', 'Seller'],
            $data, ['text' => ['B', 'C', 'E'], 'money' => ['I'], 'dates' => ['A' => self::STAMP, 'H' => self::STAMP], 'wide' => ['D' => 26, 'F' => 22, 'J' => 22]]);
        $this->colourText($s, 'G4:G' . (count($data) + 3), ['Booked' => '1D4ED8', 'Expired' => 'B91C1C', 'Cancelled' => 'B91C1C']);
        return count($data);
    }

    private function changes(Worksheet $s, Collection $changes): void
    {
        $kinds = ['reissue' => 'Re-issue', 'reschedule' => 'Reschedule', 'refund' => 'Refund', 'void' => 'Void', 'newticket' => 'New ticket after refund'];
        $data = $changes->map(fn ($c) => [$this->xlDate($c['date']), $kinds[$c['kind']] ?? ucfirst($c['kind']), $c['pnr'], $c['passenger'], $c['client'],
            $c['cost_added'], $c['charged'], $c['reversed'], $c['revenue'], $c['note']])->all();
        $this->table($s, 'Changes — re-issues, reschedules, refunds and voids (Ticketing only; contract flights are on Flight Accounts)',
            ['Date', 'Kind', 'PNR', 'Passenger', 'Agent', 'Cost added', 'Charged to agent', 'Agent price reversed', 'Net to revenue', 'Note'],
            $data, ['text' => ['C'], 'money' => ['F', 'G', 'H', 'I'], 'dates' => ['A' => self::STAMP], 'wide' => ['B' => 22, 'D' => 26, 'E' => 22, 'J' => 60]]);
        $n = count($data) + 3;
        $this->colourText($s, "B4:B{$n}", ['New ticket' => '15803D', 'Refund' => 'B91C1C', 'Void' => 'B91C1C', 'Re-issue' => 'B45309', 'Reschedule' => '1D4ED8']);
        $this->signColour($s, "I4:I{$n}");
        $this->totals($s, $n, ['F', 'G', 'H', 'I']);
    }

    private function sellers(Worksheet $s, Collection $sheet): void
    {
        $data = $sheet->map(fn ($v) => [$v['name'] . ($v['portal'] ? ' (portal)' : ''), $v['portal'] ? 'Portal' : 'Vendor', $v['tickets'], $v['billed'], $v['paid'], $v['refunds'], $v['due'],
            $v['due_items'], $this->xlDate($v['oldest']), $v['portal'] ? $v['wallet'] : $v['ledger']])->all();
        $this->table($s, 'Sellers — what each vendor / portal billed, was paid, refunded and is still owed',
            ['Seller', 'Type', 'Tickets', 'Billed', 'Paid', 'Refunds', 'We owe', 'Unpaid tickets', 'Oldest unpaid', 'Ledger / wallet balance'],
            $data, ['money' => ['D', 'E', 'F', 'G', 'J'], 'dates' => ['I' => self::DATE], 'kind' => ['C' => 'count', 'H' => 'count'], 'wide' => ['A' => 30]]);
        $n = count($data) + 3;
        $this->bar($s, "G4:G{$n}", 'F87171');
        $this->totals($s, $n, ['C', 'D', 'E', 'F', 'G', 'H'], ['C', 'H']);
    }

    private function agents(Worksheet $s, Collection $sheet): void
    {
        $data = $sheet->map(fn ($a) => [$a['name'], $a['invoices'], $a['tickets'], $a['charged'], $a['received'], $a['due'], $this->xlDate($a['oldest']), $a['days'], $a['ledger']])->all();
        $this->table($s, 'Agents — what each agent was charged, has paid and still owes us',
            ['Agent', 'Invoices', 'Tickets', 'Charged', 'Received', 'Owes us', 'Oldest unpaid', 'Days waiting', 'Ledger balance'],
            $data, ['money' => ['D', 'E', 'F', 'I'], 'dates' => ['G' => self::DATE], 'kind' => ['B' => 'count', 'C' => 'count', 'H' => 'count'], 'wide' => ['A' => 30]]);
        $n = count($data) + 3;
        $this->bar($s, "F4:F{$n}", '60A5FA');
        // waiting a month or more stands out
        $c = (new Conditional())->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)->addCondition(30);
        $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
        $s->getStyle("H4:H{$n}")->setConditionalStyles([$c]);
        $this->totals($s, $n, ['B', 'C', 'D', 'E', 'F'], ['B', 'C']);
    }

    /** Oldest month first, so the chart reads left to right. Columns G–I feed the Dashboard chart. */
    private function monthly(Worksheet $s, Collection $monthly): int
    {
        $data = $monthly->reverse()->values()->map(fn ($m) => [$m['label'], $m['tickets'], $m['cost'], $m['penalty'], $m['cf_cost'], $m['idle_cost'],
            $m['revenue'], $m['sold_cost'], $m['profit'], $m['margin'] === null ? null : $m['margin'] / 100])->all();
        // this tab has no title row: the chart reads row 1 as its series names
        $s->fromArray(['Month', 'Tickets', 'Ticket cost', 'Airline penalties', 'On contract flights', 'Not sold yet', 'Revenue', 'Sold cost', 'Profit', 'Margin'], null, 'A1');
        if ($data) {
            $s->fromArray($data, null, 'A2', true);
        }
        $this->record($s->getTitle(), 'Monthly P&L — cost, revenue and profit by month',
            ['Month', 'Tickets', 'Ticket cost', 'Airline penalties', 'On contract flights', 'Not sold yet', 'Revenue', 'Sold cost', 'Profit', 'Margin'],
            $data, ['money' => ['C', 'D', 'E', 'F', 'G', 'H', 'I'], 'kind' => ['B' => 'count', 'J' => 'percent']]);
        $this->views[$s->getTitle()]['totals'] = [1, 2, 3, 4, 5, 6, 7, 8];
        $n = count($data) + 1;
        $this->headStyle($s, 'A1:J1');
        $s->freezePane('B2');
        $s->setAutoFilter("A1:J{$n}");
        $s->getStyle("C2:I{$n}")->getNumberFormat()->setFormatCode(self::MONEY);
        $s->getStyle("J2:J{$n}")->getNumberFormat()->setFormatCode('0.0%');
        $this->signColour($s, "I2:I{$n}");
        $this->zebra($s, 2, $n, 'J');
        foreach (range('A', 'J') as $c) {
            $s->getColumnDimension($c)->setWidth($c === 'A' ? 12 : 16);
        }
        return count($data);
    }

    // ── shared formatting ────────────────────────────────────────────────

    /**
     * Title on row 1, a back-link on row 2, headers on row 3, data from row 4:
     * frozen header, filter on every column, money and dates formatted,
     * striped rows, sensible widths.
     */
    private function table(Worksheet $s, string $title, array $head, array $data, array $opt): void
    {
        $this->record($s->getTitle(), $title, $head, $data, $opt);
        $last = Coordinate::stringFromColumnIndex(count($head));
        $s->setCellValue('A1', $title);
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1E293B');
        $s->setCellValue('A2', '← Dashboard');
        $s->getCell('A2')->getHyperlink()->setUrl("sheet://'Dashboard'!A1");
        $s->getStyle('A2')->getFont()->setUnderline(true)->getColor()->setRGB(self::BRAND);
        $s->fromArray($head, null, 'A3');
        $this->headStyle($s, "A3:{$last}3");
        $n = max(4, count($data) + 3);
        if ($data) {
            $s->fromArray($data, null, 'A4', true);
            // PNR, e-ticket and passport are codes, not numbers: "1572412345670" must not become 1.57E+12
            foreach ($opt['text'] ?? [] as $c) {
                $i = Coordinate::columnIndexFromString($c) - 1;
                foreach ($data as $k => $row) {
                    if ($row[$i] !== null && $row[$i] !== '') {
                        $s->setCellValueExplicit($c . ($k + 4), (string) $row[$i], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                }
            }
        } else {
            $s->setCellValue('A4', 'Nothing yet.');
        }
        $s->freezePane('A4');
        $s->setAutoFilter("A3:{$last}{$n}");
        foreach ($opt['money'] ?? [] as $c) {
            $s->getStyle("{$c}4:{$c}{$n}")->getNumberFormat()->setFormatCode(self::MONEY);
        }
        foreach ($opt['dates'] ?? [] as $c => $fmt) {
            $s->getStyle("{$c}4:{$c}{$n}")->getNumberFormat()->setFormatCode($fmt);
        }
        for ($i = 1, $k = count($head); $i <= $k; $i++) {
            $c = Coordinate::stringFromColumnIndex($i);
            $dateW = isset($opt['dates'][$c]) ? ($opt['dates'][$c] === self::STAMP ? 18 : 13) : null;
            $s->getColumnDimension($c)->setWidth($opt['wide'][$c] ?? $dateW ?? (in_array($c, $opt['money'] ?? [], true) ? 15 : 14));
        }
        $s->getStyle("A4:{$last}{$n}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $this->zebra($s, 4, $n, $last);
    }

    /** Keep a tab's data for the viewer: column kinds from the sheet's own formats. */
    private function record(string $name, string $title, array $head, array $data, array $opt): void
    {
        $kinds = [];
        foreach ($head as $i => $h) {
            $c = Coordinate::stringFromColumnIndex($i + 1);
            $kinds[$i] = in_array($c, $opt['money'] ?? [], true) ? 'money'
                : (isset($opt['dates'][$c]) ? ($opt['dates'][$c] === self::STAMP ? 'stamp' : 'date')
                : ($opt['kind'][$c] ?? 'text'));
        }
        $this->views[$name] = ['name' => $name, 'title' => $title, 'head' => $head, 'rows' => $data, 'kinds' => $kinds, 'totals' => []];
    }

    private function headStyle(Worksheet $s, string $range): void
    {
        $s->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $s->getRowDimension((int) preg_replace('/\D/', '', explode(':', $range)[0]))->setRowHeight(30);
    }

    private function heading(Worksheet $s, string $cell): void
    {
        $s->getStyle($cell)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E293B');
    }

    private function zebra(Worksheet $s, int $from, int $to, string $last): void
    {
        for ($r = $from + 1; $r <= $to; $r += 2) {
            $s->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F7FB');
        }
        $s->getStyle("A{$from}:{$last}{$to}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
    }

    /** A bold totals row under the data, as live SUM formulas (they follow the filter-free sum). */
    private function totals(Worksheet $s, int $lastRow, array $cols, array $counts = []): void
    {
        if ($lastRow < 4) {
            return;
        }
        if (isset($this->views[$s->getTitle()])) {
            $this->views[$s->getTitle()]['totals'] = array_map(fn ($c) => Coordinate::columnIndexFromString($c) - 1, $cols);
        }
        $r = $lastRow + 1;
        $s->setCellValue("A{$r}", 'Total');
        foreach ($cols as $c) {
            // SUBTOTAL(109) — the total follows the filter, so a filtered list shows its own total
            $s->setCellValue("{$c}{$r}", "=SUBTOTAL(109,{$c}4:{$c}{$lastRow})");
            $s->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode(in_array($c, $counts, true) ? '#,##0' : self::MONEY);
        }
        $last = $s->getHighestColumn();
        $s->getStyle("A{$r}:{$last}{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BRAND]]],
        ]);
    }

    private function colourText(Worksheet $s, string $range, array $map): void
    {
        $conds = [];
        foreach ($map as $text => $rgb) {
            $c = (new Conditional())->setConditionType(Conditional::CONDITION_CONTAINSTEXT)->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)->setText($text);
            $c->addCondition('NOT(ISERROR(SEARCH("' . $text . '",' . explode(':', $range)[0] . ')))');
            $c->getStyle()->getFont()->setBold(true)->getColor()->setRGB($rgb);
            $conds[] = $c;
        }
        $s->getStyle($range)->setConditionalStyles($conds);
    }

    private function signColour(Worksheet $s, string $range): void
    {
        $neg = (new Conditional())->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_LESSTHAN)->addCondition(0);
        $neg->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
        $pos = (new Conditional())->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition(0);
        $pos->getStyle()->getFont()->getColor()->setRGB('15803D');
        $s->getStyle($range)->setConditionalStyles([$neg, $pos]);
    }

    /** An in-cell data bar, so the biggest dues stand out in a long list. */
    private function bar(Worksheet $s, string $range, string $rgb): void
    {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_DATABAR);
        $c->setDataBar((new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ConditionalDataBar())
            ->setMinimumConditionalFormatValueObject(new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ConditionalFormatValueObject('min'))
            ->setMaximumConditionalFormatValueObject(new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ConditionalFormatValueObject('max'))
            ->setColor('FF' . $rgb));
        $s->getStyle($range)->setConditionalStyles([$c]);
    }

    private function xlDate($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $ts = strtotime((string) $v);
        return $ts ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($ts + (int) date('Z', $ts)) : null;
    }

    private function next(string $col): string
    {
        return Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1);
    }
}
