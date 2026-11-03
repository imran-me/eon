<?php

namespace App\Services;

use App\Http\Controllers\Dashboard\PartyStatementController;
use App\Models\Company;
use App\Models\User;
use App\Support\StatementRows;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NumberToWords\NumberToWords;

/**
 * The sales invoice handed to an agent / client (owner, 2026-10-14): not the
 * ledger, but the sales themselves — one, several or all of them, picked like
 * Print picks rows. Each line is a ledger charge, numbered by its own
 * reference (CF-033-94, INV000037, VINV-021 …); no separate invoice series.
 *
 * One dompdf sheet (party-statement/pdf-sales-invoice) serves the Invoice
 * button and the after-posting email, so the agent gets the same paper either
 * way. Reads only.
 */
class SalesInvoice
{
    /** Ledger types that are money moving, not something sold. */
    private const NOT_SALES = [
        'opening_balance', 'party_payment', 'party_payment_reversal', 'bank_transfer', 'vendor_refund',
        'customer_advance_received', 'customer_advance_received_reversal',
        'customer_advance_refund', 'customer_advance_refund_reversal',
        'customer_service_refund', 'customer_service_refund_reversal',
        'supplier_advance_paid', 'supplier_advance_paid_reversal',
    ];

    /** A row that belongs on a sales invoice: receivable side, a charge or a credit against one. */
    public static function isSale($t): bool
    {
        return ($t->user_type ?? null) !== 'supplier'
            && ! in_array($t->type, self::NOT_SALES, true)
            && ((float) $t->debit > 0 || (float) $t->credit > 0);
    }

    /** Whether any of these rows is a charge — what makes an email carry an invoice. */
    public static function hasCharge(Collection $transactions): bool
    {
        return $transactions->contains(fn ($t) => self::isSale($t) && (float) $t->debit > 0);
    }

    /**
     * The invoice lines for these ledger rows, in ledger order: StatementRows
     * rows (the same words as the statement), payments and advances dropped.
     */
    public static function lines(Collection $transactions): Collection
    {
        $rows = $transactions->filter(fn ($t) => self::isSale($t))->values();
        if ($rows->isEmpty()) {
            return collect();
        }
        [$passengers, $saleItems] = StatementRows::related($rows);

        return StatementRows::build($rows, $passengers, $saleItems, false)->filter(fn ($r) => ! $r['is_payment'])->values();
    }

    /**
     * One line as the Invoice window lists it — the same words as the PDF
     * (StatementRows::cell), so what is ticked is what gets printed.
     */
    public static function summary(array $r): array
    {
        $c = StatementRows::cell($r);
        $travel = ($r['passenger'] || $r['sale_items']) && $r['flight_date'];

        return [
            'id' => $r['id'],
            'date' => $r['posted']->format('Y-m-d'),
            'day' => $r['posted']->format('d M Y'),
            'ref' => $r['ref'] !== '—' ? $r['ref'] : null,
            'kind' => $r['kind'],
            'item2' => $r['flag'] ? '⚠ '.$r['flag'] : ($travel ? 'Flies '.$r['flight_date']->format('d M Y') : $c['kind2']),
            'flag' => (bool) $r['flag'],
            'p1' => $c['p1'],
            'p2' => $c['p2'],
            'pnr' => $c['pnr'],
            'debit' => round((float) $r['debit'], 2),
            'credit' => round((float) $r['credit'], 2),
        ];
    }

    /**
     * Fields of the full record (reasonsFor's detail) an agent may see, and
     * what the invoice calls them. Anything not listed — the vendor / provider,
     * our cost, the party's own name — never reaches the invoice.
     */
    private const DETAIL_FIELDS = [
        'Passenger' => 'Passenger', 'Applicant' => 'Passenger', 'PNR / Passenger' => 'Passenger',
        'Passport' => 'Passport', 'Ticket' => 'Airline', 'Route' => 'Route', 'Flight' => 'Flight',
        'Departs' => 'Departs', 'Category / Class' => 'Category', 'Category / Type' => 'Visa type',
        'Services' => 'Services', 'Stage' => 'Status', 'File' => 'File no.',
        'Country / Category' => 'Country', 'Country' => 'Country', 'Submitted' => 'Submitted',
        'Expected' => 'Expected', 'Duration' => 'Duration', 'Travel' => 'Travel date',
        'Application' => 'Application', 'Price' => 'Price', 'Sale price' => 'Price',
        'Invoice' => 'Invoice', 'Files' => 'Files', 'Sold' => 'Sold',
    ];

    /** The order a line's facts are printed in. */
    private const FACT_ORDER = ['Passport', 'File no.', 'Country', 'Visa type', 'Route', 'PNR', 'Airline', 'Flight', 'Departs', 'Travel date', 'Category', 'Services', 'Submitted', 'Expected', 'Duration', 'Application', 'Invoice', 'Files', 'Sold', 'Status'];

    /** Which part of the business a line is — the invoice groups and names it by this. */
    private const SERVICE = [
        'contract_flight_passenger_agent' => ['flight', 'Contract flight package'],
        'contract_flight_passenger_reissue' => ['flight', 'Flight reissue'],
        'ticket_sale' => ['ticket', 'Air ticket'],
        'contract_file_client' => ['file', 'File processing'],
        'contract_file_client_adjustment' => ['file', 'File adjustment'],
        'visa_sale' => ['visa', 'Visa processing'],
        'party_invoice' => ['other', 'Custom service'],
    ];

    /**
     * Everything the invoice can say about one line, readable to the agent:
     * group / service name, the main name, labelled facts (one record) and,
     * when the sale covers several people (a ticket invoice, a visa invoice),
     * one row per person with its own price.
     *
     * @return array{group: string, service: string, title: string, facts: array<int, array{0: string, 1: string}>, people: array<int, array<string, string>>, note: ?string}
     */
    public static function details(array $r): array
    {
        [$group, $service] = self::SERVICE[$r['type']] ?? ['other', $r['kind']];
        $tidy = fn ($v) => trim(preg_replace('/\s{2,}/u', ' ', str_replace('৳', 'BDT ', (string) $v)));
        // "Shahjalal International Airport (DAC) → Ercan International Airport (ECN)" → "DAC → ECN"
        $codes = fn ($v) => trim(preg_replace('/\s*→\s*/u', ' → ', preg_replace('/[^()→·]*\((\w{3})\)/u', '$1', (string) $v)));

        // The record behind the row, cut to what the agent may see.
        $people = [];
        if (! empty($r['detail']['columns'])) {
            foreach ($r['detail']['rows'] as $row) {
                $one = [];
                foreach ($r['detail']['columns'] as $i => $col) {
                    $label = self::DETAIL_FIELDS[$col] ?? null;
                    $value = $row[$i] ?? null;
                    if (! $label || $value === null || $value === '' || ! is_scalar($value)) {
                        continue;
                    }
                    if ($col === 'PNR / Passenger') {
                        // "S42FY6 · MD NAME" — or the PNR alone when the ticket has no holder yet.
                        [$pnr, $name] = array_pad(explode(' · ', (string) $value, 2), 2, null);
                        $one['PNR'] = $pnr;
                        if ($name) {
                            $one['Passenger'] = $name;
                        }

                        continue;
                    }
                    $value = $label === 'Route' ? $codes($value) : $tidy($value);
                    if ($label === 'Status') {
                        $value = ucfirst(strtolower($value));
                    }
                    $one[$label] = $value;
                }
                if ($one) {
                    $people[] = $one;
                }
            }
        }

        // A contract-flight passenger: his legs say more than the record's one route.
        if ($r['legs']) {
            $p = &$people[0];
            $p = $p ?? [];
            $p['Route'] = str_replace('→', ' → ', $r['route']);
            $p['PNR'] = collect($r['legs'])->pluck('pnr')->filter()->unique()->implode(', ');
            $p['Passenger'] = $p['Passenger'] ?? $r['passenger'];
            $p['Passport'] = $p['Passport'] ?? $r['passport'];
            unset($p);
        }

        // When the ticket flies — the record has no column for it; the sale's own
        // tickets do (matched by PNR), and so does a passenger's first leg.
        foreach ($people as &$one) {
            if (isset($one['Departs']) || isset($one['Travel date'])) {
                continue;
            }
            $item = collect($r['sale_items'])->first(fn ($it) => ($it['pnr'] ?? null) && ($it['pnr'] === ($one['PNR'] ?? null)));
            $when = $item['date'] ?? ($r['flight_date'] ?? null);
            if ($when) {
                $one['Travel date'] = Carbon::parse($when)->format('d/m/Y');
            }
        }
        unset($one);

        $first = $people[0] ?? [];
        // Whose sale it is; a line with nobody behind it (a custom service) is named by
        // its service, and its note becomes the description below — never both.
        $title = $r['who'][0] ?? ($first['Passenger'] ?? ($r['passenger'] ?: $service));
        if (count($people) > 1) {
            $title = ($first['Passenger'] ?? $title).' +'.(count($people) - 1).' more';
        }

        // One record: labelled facts, in the order a reader looks for them. Several:
        // the people table carries them. A row with no record (a reversal, a
        // penalty, a custom service) is described by its note, minus the name
        // that already heads it.
        $facts = [];
        if (count($people) <= 1) {
            foreach (self::FACT_ORDER as $label) {
                if (isset($first[$label])) {
                    $facts[] = [$label, $first[$label]];
                }
            }
            if (! $facts && $r['remarks'] !== '') {
                $note = StatementRows::tidy($r['remarks']);
                foreach ((array) $r['who'] as $name) {
                    $note = preg_replace('/\s*[—·-]\s*'.preg_quote($name, '/').'(?=\s|\(|$)/iu', '', $note);
                }
                $facts[] = ['Description', trim($note)];
            }
        }

        return [
            'group' => $group,
            'service' => $service,
            'title' => $title,
            'facts' => $facts,
            'people' => count($people) > 1 ? $people : [],
            // The flag, unless the status line already says it ("Ticket issued · rescheduled").
            'note' => $r['flag'] && stripos($first['Status'] ?? '', $r['flag']) === false ? '⚠ '.$r['flag'] : null,
        ];
    }

    /**
     * Everything the sheet prints: party, company, lines with their details,
     * totals, the amount in words, the references, the sale period and the
     * party's balance today (same company). $extra: issued (Carbon).
     */
    public static function data(User $party, ?Company $company, Collection $lines, array $extra = []): array
    {
        $type = app(PartyNotifier::class)->typeOf($party) ?? 'client';
        $meta = PartyStatementController::TYPES[$type];
        $company = $company ?? Company::find(2) ?? Company::first();
        $charges = (float) $lines->sum('debit');
        $credits = (float) $lines->sum('credit');
        $total = $charges - $credits;

        try {
            $words = ucfirst((new NumberToWords)->getNumberTransformer('en')->toWords((int) round(abs($total)))).' Taka only';
        } catch (\Throwable) {
            $words = 'BDT '.number_format(abs($total), 2);
        }

        $logoPath = $company?->logo ? public_path($company->logo) : null;
        if (! $logoPath || ! is_file($logoPath)) {
            $logoPath = public_path('logo.png');
        }
        [$logo, $logoSize] = self::image($logoPath, 150, 78);
        // The signature above "Authorized Signatory" (Invoice window → ⚙), when one is set.
        [$signature, $signatureSize] = $company?->signature ? self::image(public_path($company->signature), 150, 56) : [null, [0, 0]];

        $dates = $lines->pluck('posted');
        $balance = null;
        try {
            $balance = app(PartyLedgerService::class)->currentBalance($party->id, $company?->id);
        } catch (\Throwable) {
        }

        return $extra + [
            'party' => $party,
            'company' => $company,
            'companyName' => $company?->name ?: 'EPAL TRAVELS & CONSULTANCY',
            'logo' => $logo,
            'logoSize' => $logoSize,
            'signature' => $signature,
            'signatureSize' => $signatureSize,
            'code' => PartyStatementController::partyCode($party->id, $type),
            'typeLabel' => $meta['label'],
            'lines' => $lines,
            'details' => $lines->map(fn ($r) => self::details($r))->all(),
            'charges' => $charges,
            'credits' => $credits,
            'total' => $total,
            'words' => $words,
            'refs' => $lines->pluck('ref')->filter(fn ($r) => $r && $r !== '—')->unique()->values(),
            'period' => $dates->isEmpty() ? '—' : ($dates->min()->isSameDay($dates->max()) ? $dates->min()->format('d M Y') : $dates->min()->format('d M Y').' – '.$dates->max()->format('d M Y')),
            'balance' => $balance,
            'issued' => $extra['issued'] ?? now(),
        ];
    }

    /**
     * An image for the sheet (the logo, the signature), as a data URI and its
     * size in px fitted into a $boxW × $boxH box, keeping its shape. The logo
     * fills 150 × 78 (owner, 2026-10-14: "make the logo bigger"). The Travels
     * logo is uploaded as a 1080 px square that is mostly white margin, so the
     * margin is trimmed first — the mark itself fills the box; a signature
     * scanned on white paper gets the same. Any failure falls back to the file
     * as it is.
     *
     * @return array{0: ?string, 1: array{0: int, 1: int}}
     */
    private static function image(string $path, int $boxW, int $boxH): array
    {
        if (! is_file($path)) {
            return [null, [0, 0]];
        }
        $fit = function (int $w, int $h) use ($boxW, $boxH): array {
            $scale = min($boxW / max(1, $w), $boxH / max(1, $h));

            return [(int) round($w * $scale), (int) round($h * $scale)];
        };
        try {
            if (function_exists('imagecropauto') && ($im = @imagecreatefromstring(file_get_contents($path)))) {
                $cut = @imagecropauto($im, IMG_CROP_THRESHOLD, 0.2, 0xFFFFFF);
                if ($cut && imagesx($cut) > 10 && imagesy($cut) > 10) {
                    $im = $cut;
                }
                imagealphablending($im, false);
                imagesavealpha($im, true);   // a transparent PNG stays transparent
                ob_start();
                imagepng($im);
                $png = ob_get_clean();

                return ['data:image/png;base64,'.base64_encode($png), $fit(imagesx($im), imagesy($im))];
            }
        } catch (\Throwable) {
        }
        $dim = @getimagesize($path) ?: [48, 48];

        return ['data:image/'.pathinfo($path, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($path)), $fit((int) $dim[0], (int) $dim[1])];
    }

    /** The PDF, or null when it cannot be rendered (an email then goes without it). */
    public static function pdf(array $data): ?string
    {
        try {
            return Pdf::loadView('party-statement.pdf-sales-invoice', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('isFontSubsettingEnabled', true)
                ->output();
        } catch (\Throwable $e) {
            Log::warning('Sales invoice PDF failed to render.', ['user_id' => $data['party']->id ?? null, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** "Invoice-CF-033-94.pdf" for one line, "Invoice-EP-AG-152-20261014.pdf" for several. */
    public static function fileName(array $data): string
    {
        $lines = $data['lines'];
        $ref = $lines->count() === 1 && ($lines->first()['ref'] ?? '—') !== '—' ? $lines->first()['ref'] : $data['code'].'-'.$data['issued']->format('Ymd');

        return 'Invoice-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $ref).'.pdf';
    }
}
