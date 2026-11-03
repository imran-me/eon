<?php

namespace App\Services;

use App\Http\Controllers\Dashboard\PartyStatementController;
use App\Models\Company;
use App\Models\PartyLedgerNotification;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The one place that talks to a party (agent / customer / vendor) about
 * their ledger — by email with the statement attached as PDF, and by SMS.
 *
 * Three messages share it, differing only in framing (`mode`):
 *
 *   activity   "new entries on your ledger" — queued automatically by
 *              TransactionObserver -> NotifyPartyLedgerActivity after a
 *              posting. Email + SMS.
 *   statement  the ledger for a period, on request — the Email button on the
 *              Party Statement page. Email only.
 *   reminder   "your outstanding due is X" — the Reminder button. Email + SMS,
 *              and only for a receivable party who actually owes something.
 *
 * Every send, whichever mode, writes one party_ledger_notifications row with
 * the per-channel outcome. Email and SMS are independently try/caught: a dead
 * SMTP never costs the SMS and vice versa, and a PDF that fails to render
 * costs only the attachment.
 */
class PartyNotifier
{
    /** Same labels the Party Statement screens use (dashboard.blade $kindLabel). */
    public const TYPE_LABELS = [
        'party_discount' => 'Discount allowed', 'party_discount_reversal' => 'Discount reversed',
        'party_payment' => 'Payment', 'party_invoice' => 'Invoice',
        'ticket_package_inclusion' => 'Ticket package adjustment',
        'ticket_sale' => 'Ticket', 'ticket_purchase' => 'Ticket purchase', 'ticket_purchase_refund' => 'Ticket cancelled — refund', 'contract_flight_reissue_fee' => 'Reissue fee', 'ticket_change_fee' => 'Ticket re-issue charge', 'ticket_change_fare' => 'Fare difference', 'contract_flight_reissue_fare' => 'Fare difference', 'ticket_change_charge' => 'Ticket change charge', 'ticket_sale_reversal' => 'Ticket sale reversed', 'vendor_refund' => 'Refund received', 'flight_sale' => 'Flight',
        'file_sale' => 'File', 'visa_sale' => 'Visa', 'visa_process' => 'Visa processing',
        'contract_file_client' => 'Contract file', 'contract_file_client_adjustment' => 'Contract file adjustment',
        'contract_file' => 'Contract file vendor cost', 'contract_file_vendor_adjustment' => 'Contract file vendor adjustment',
        'contract_file_service_cost' => 'Contract file service cost',
        'contract_flight_passenger_agent' => 'Flight passenger', 'opening_balance' => 'Opening balance',
        'contract_flight_passenger_agent_reversal' => 'Flight package reversal', 'contract_flight_airline_penalty' => 'Airline penalty', 'contract_flight_fare_difference' => 'Fare difference',
        'contract_flight_price_revision' => 'Flight package revised',   // a cancelled return lowered it (2026-10-14)
    ];

    public function __construct(private PartyLedgerService $ledger) {}

    // ── The three messages ──────────────────────────────────────────────────

    /**
     * New entries on the ledger. $entries are the raw rows to announce; the
     * watermark ids are recorded so the next sweep starts after them.
     */
    public function sendActivity(User $party, string $type, Collection $entries, int $fromId, int $toId): PartyLedgerNotification
    {
        // Whole ledger with balances recomputed from debit/credit (never the
        // stored snapshot — see PartyLedgerService), so the "balance after"
        // printed beside each new entry agrees with the statement attached.
        [$rows, $opening, $closing] = $this->ledger->statement($party->id);
        $rows = $this->ledger->activeRows($rows, (float) $opening);
        $entries = $entries->whereIn('id', $rows->pluck('id'))->values();
        $byId = $rows->keyBy('id');
        $entries = $entries->map(function (Transaction $t) use ($byId) {
            $t->balance = isset($byId[$t->id]) ? (float) $byId[$t->id]->balance : (float) $t->balance;

            return $t;
        });

        $data = $this->context($party, $type, 'activity', $entries, (float) $closing, [
            'rows' => $rows, 'opening' => (float) $opening,
        ]);

        return $this->deliver($party, $data, $this->smsForActivity($data), 'activity', null, $fromId, $toId);
    }

    /** The statement for a period (whole ledger when no dates), emailed on request. No SMS. */
    public function sendStatement(User $party, ?string $from, ?string $to, ?int $sentBy): PartyLedgerNotification
    {
        return $this->deliver($party, $this->statementContext($party, $from, $to), null, 'statement', $sentBy, 0, 0, true, false);
    }

    /** Send the statement summary by SMS only. Email and WhatsApp are separate UI actions. */
    public function sendStatementSms(User $party, ?string $from, ?string $to, ?int $sentBy): PartyLedgerNotification
    {
        $data = $this->statementContext($party, $from, $to);
        $balance = rtrim(self::balanceLine($data['closing'], $data['side']), '.');
        $text = "Dear {$party->name}, {$balance} as of ".now()->format('d/m/Y')
            .". Ref {$data['code']}. Please check your email for the statement.";

        return $this->deliver($party, $data, $text, 'statement_sms', $sentBy, 0, 0, false, true);
    }

    /**
     * The statement PDF on its own — what the short WhatsApp link serves
     * (PartyStatementController::shortPdf). Same sheet the email attaches.
     */
    public function statementPdfFor(User $party, ?string $from, ?string $to): ?string
    {
        return $this->statementPdf($this->statementContext($party, $from, $to));
    }

    private function statementContext(User $party, ?string $from, ?string $to): array
    {
        $type = $this->typeOf($party) ?? 'client';
        [$rows, $opening, $closing] = $this->ledger->statement($party->id, $from, $to);
        $rows = $this->ledger->activeRows($rows, (float) $opening);

        // The email lists the period's rows (the PDF carries all of them);
        // nothing is "new", so nothing is highlighted.
        return $this->context($party, $type, 'statement', $rows, (float) $closing, [
            'rows' => $rows, 'opening' => (float) $opening, 'highlight' => collect(),
            'date_from' => $from, 'date_to' => $to,
        ]);
    }

    /**
     * Payment reminder. Only meaningful for a receivable party with a positive
     * balance — the caller checks reminderAllowed() first; this re-checks.
     */
    public function sendReminder(User $party, ?int $sentBy): PartyLedgerNotification
    {
        $type = $this->typeOf($party) ?? 'client';
        $closing = $this->ledger->currentBalance($party->id);
        if (! $this->reminderAllowed($type, $closing)) {
            throw new \RuntimeException('Nothing is outstanding on this ledger.');
        }

        [$rows, $opening] = $this->ledger->statement($party->id);
        $rows = $this->ledger->activeRows($rows, (float) $opening);
        $data = $this->context($party, $type, 'reminder', collect(), $closing, [
            'rows' => $rows, 'opening' => (float) $opening,
        ]);

        return $this->deliver($party, $data, $this->smsForReminder($data), 'reminder', $sentBy);
    }

    public function reminderAllowed(string $type, float $closing): bool
    {
        return (PartyStatementController::TYPES[$type]['side'] ?? null) === 'receivable' && $closing > 0.005;
    }

    // ── Shared machinery ───────────────────────────────────────────────────

    public function typeOf(User $user): ?string
    {
        foreach (PartyStatementController::TYPES as $type => $meta) {
            if ($user->hasRole($meta['role'])) {
                return $type;
            }
        }

        return null;
    }

    /** The view data every template (email body, PDF, SMS) reads. */
    private function context(User $party, string $type, string $mode, Collection $entries, float $closing, array $extra = []): array
    {
        $meta = PartyStatementController::TYPES[$type];

        return $extra + [
            'party' => $party,
            'type' => $type,
            'typeLabel' => $meta['label'],
            'side' => $meta['side'],
            'code' => PartyStatementController::partyCode($party->id, $type),
            // Party ledgers belong to the travel operation. Company::first()
            // incorrectly branded messages as EPAL IT SOLUTIONS (company 1).
            'company' => Company::find(2) ?? Company::first(),
            'mode' => $mode,
            'entries' => $entries,
            'highlight' => $entries,
            'closing' => $closing,
            'labels' => self::TYPE_LABELS,
            'date_from' => null,
            'date_to' => null,
        ];
    }

    private function deliver(User $party, array $data, ?string $smsText, string $kind, ?int $sentBy, int $fromId = 0, int $toId = 0, bool $sendEmail = true, bool $sendSms = true): PartyLedgerNotification
    {
        $log = new PartyLedgerNotification([
            'user_id' => $party->id,
            'party_type' => $data['type'],
            'kind' => $kind,
            'sent_by' => $sentBy,
            'from_transaction_id' => $fromId,
            'to_transaction_id' => $toId,
            'entry_count' => $data['entries']->count(),
            'closing_balance' => $data['closing'],
        ]);

        // ── Email ──────────────────────────────────────────────────────────
        $email = trim((string) $party->email);
        if (! $sendEmail) {
            $log->email_status = null;
        } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $log->email_to = $email;
            try {
                $pdf = $this->statementPdf($data);
                $attachments = $pdf ? [[
                    'name' => 'Statement-'.$data['code'].'-'.now()->format('Ymd').'.pdf',
                    'content' => $pdf,
                ]] : [];

                // New entries that include a sale also carry the sales invoice
                // for them (owner, 2026-10-14) — the same sheet the Invoice
                // button gives. Any failure leaves it off; the email still goes.
                if ($kind === 'activity' && SalesInvoice::hasCharge($data['entries'])) {
                    try {
                        $inv = SalesInvoice::data($party, $data['company'] ?? null, SalesInvoice::lines($data['entries']));
                        if ($inv['lines']->isNotEmpty() && ($invPdf = SalesInvoice::pdf($inv))) {
                            $attachments[] = ['name' => SalesInvoice::fileName($inv), 'content' => $invPdf];
                            $data['invoiceAttached'] = true;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Sales invoice for the ledger email failed.', ['user_id' => $party->id, 'error' => $e->getMessage()]);
                    }
                }

                // Use the same Brevo transactional-email API as attendance
                // notices and payslips. The helper also base64-encodes the PDF
                // in Brevo's required attachment format.
                $response = sendBrevoMail(
                    $email,
                    $party->name,
                    $this->emailSubject($data),
                    view('emails.party-ledger-activity', $data)->render(),
                    $attachments,
                );

                if (! $response->successful()) {
                    throw new \RuntimeException(
                        'Brevo HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500)
                    );
                }
                $log->email_status = 'sent';
            } catch (\Throwable $e) {
                $log->email_status = 'failed';
                $log->email_error = mb_substr($e->getMessage(), 0, 2000);
                Log::warning('Party ledger email failed.', ['user_id' => $party->id, 'kind' => $kind, 'error' => $e->getMessage()]);
            }
        } else {
            $log->email_status = 'skipped';
        }

        // ── SMS ────────────────────────────────────────────────────────────
        $phone = (string) $party->phone;
        if (! $sendSms || $smsText === null) {
            $log->sms_status = null;                 // this message has no SMS leg
        } elseif ($phone !== '') {
            $log->sms_to = $phone;
            try {
                $result = sendSms($phone, $smsText);
                Log::info('Party ledger SMS gateway response.', [
                    'user_id' => $party->id,
                    'kind' => $kind,
                    'phone_tail' => substr($phone, -4),
                    'response' => mb_substr((string) $result, 0, 500),
                ]);

                if ($result === false) {
                    $log->sms_status = 'failed';
                    $log->sms_error = mb_substr(
                        app()->bound('sms.last_error') ? (string) app('sms.last_error') : 'SMS gateway request failed.',
                        0,
                        2000
                    );
                } else {
                    $log->sms_status = 'sent';
                }
            } catch (\Throwable $e) {
                $log->sms_status = 'failed';
                $log->sms_error = mb_substr($e->getMessage(), 0, 2000);
                Log::warning('Party ledger SMS failed.', ['user_id' => $party->id, 'kind' => $kind, 'error' => $e->getMessage()]);
            }
        } else {
            $log->sms_status = 'skipped';
        }

        $log->save();

        return $log;
    }

    /**
     * The attached statement. dompdf cannot shape Bangla, so this sheet is
     * English only; the Bangla is in the email body. A render failure returns
     * null and the email goes out without the attachment rather than not at all.
     */
    private function statementPdf(array $data): ?string
    {
        try {
            return Pdf::loadView('party-statement.pdf-ledger-statement', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('isFontSubsettingEnabled', true)   // full DejaVu embeds 1.5 MB; subset is ~40 KB
                ->output();
        } catch (\Throwable $e) {
            Log::warning('Party ledger statement PDF failed to render.', ['user_id' => $data['party']->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Subject used by the Brevo transactional email request. */
    private function emailSubject(array $data): string
    {
        $code = $data['code'];
        $count = $data['entries']->count();

        return match ($data['mode'] ?? 'activity') {
            'statement' => "Your ledger statement - {$code}",
            'reminder' => 'Payment reminder - outstanding due BDT '
                .number_format($data['closing'], 2)." - {$code}",
            default => ($count === 1 ? 'New entry' : "{$count} new entries")
                ." on your ledger - {$code}",
        };
    }

    // ── SMS texts — English: a Bangla SMS bills in 70-character segments ───

    private function smsForActivity(array $d): string
    {
        $entries = $d['entries'];
        $co = $this->companyShort($d);

        if ($entries->count() === 1) {
            $t = $entries->first();
            $what = ($d['labels'][$t->type] ?? ucfirst(str_replace('_', ' ', $t->type)))
                .($t->reference_no ? " ({$t->reference_no})" : '')
                .': BDT '.number_format((float) ($t->debit > 0 ? $t->debit : $t->credit))
                .' '.($t->debit > 0 ? 'Dr' : 'Cr');
        } else {
            $dr = (float) $entries->sum('debit');
            $cr = (float) $entries->sum('credit');
            $bits = [];
            if ($dr > 0) {
                $bits[] = 'Dr BDT '.number_format($dr);
            }
            if ($cr > 0) {
                $bits[] = 'Cr BDT '.number_format($cr);
            }
            $what = $entries->count().' new entries'.($bits ? ' ('.implode(', ', $bits).')' : '');
        }

        return "Dear {$d['party']->name}, posted on your ledger with {$co}: {$what}. "
            .self::balanceLine($d['closing'], $d['side'])
            ." Ref {$d['code']}. Statement emailed.";
    }

    private function smsForReminder(array $d): string
    {
        return "Dear {$d['party']->name}, a reminder from ".$this->companyShort($d)
            .': your outstanding due is BDT '.number_format($d['closing'], 2)
            ." (Ref {$d['code']}). Please arrange payment at your earliest convenience. Thank you.";
    }

    private function companyShort(array $d): string
    {
        return $d['company']?->short_name ?: ($d['company']?->name ?: config('app.name'));
    }

    /**
     * How to read the closing figure, as words. On every party ledger debit
     * raises the balance (see PartyStatementController::TYPES) — what a
     * positive number *means* depends on which way the money runs.
     */
    public static function balanceLine(float $closing, string $side): string
    {
        $amount = 'BDT '.number_format(abs($closing), 2);
        if (abs($closing) < 0.005) {
            return 'Balance: BDT 0.00 (settled).';
        }
        if ($side === 'receivable') {
            return $closing > 0 ? "Outstanding due: {$amount}." : "Advance with us: {$amount}.";
        }

        return $closing > 0 ? "Payable to you: {$amount}." : "Advance paid to you: {$amount}.";
    }
}
