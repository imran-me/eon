<?php

namespace App\Services;

use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Models\Account;
use App\Models\ContractFlightPassengerChange as Change;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Correct ONE posted contract-flight change entry (owner, 2026-10-19: "a way so I can edit those
 * if mistakes happen — amounts, which ledger to hit, who bears"). Super admin only; opened from
 * the passenger's edit modal → Flight changes → Correct.
 *
 * Nothing is edited or deleted. What each ledger holds for the entry NOW is read from the
 * ledgers themselves — never from the entry's fields, which a mistake may have left wrong — the
 * corrected entry says what each should hold, and ONE row per difference is ADDED beside the
 * originals, dated the day staff choose. A wrong ledger is brought back to 0, the right one up to
 * the amount, and both difference rows stay visible:
 *
 *   seller   vendor row / portal wallet row + Dr 5173·5172 ⇄ Cr 2111·portal    source REISSUE_FEE_TYPE
 *   agent    his ONE change line (LEDGER_TYPE) + Dr 1311 ⇄ Cr 4181 penalty · 4183 our charge   FIX_SOURCE
 *   refund   Ticketing's own correctRefundAmount(); what the airline kept follows: 5171 ⇄ 5110
 *   fare     Company 5172 ⇄ 5110 (FARE_COMPANY_SOURCE); the agent's share ⇄ 4182 (FARE_LEDGER_TYPE)
 *
 * The same sources the first postings used, so every reader (the card, penaltyTotals, Company
 * Ledger, Flight Accounts, Party Statement) shows the corrected figure as it is. The entry's own
 * fields are set to the corrected values afterwards — they are what the screens read for "who pays".
 *
 * Per-ticket charges carry a bearer per ticket, as Re-assign records them: the agent's tickets and
 * our charge ride on the entry itself (one line on his statement — owner, 2026-09-28), our
 * tickets on a "we bear" half beside it ("same move as change #…"), and the seller's rows stay on
 * the entry whoever bears them. Our charge is always the agent's (owner, 2026-10-13): moving a
 * penalty to us leaves it billed unless it is changed too.
 */
class ContractFlightChangeCorrection
{
    use PostsPartyLedger;

    public const EVENT_CODE = 'entry_corrected';

    private const PART_LABELS = ['fee' => 'Re-issue charge', 'fare' => 'Fare difference'];

    /** Entries recorded before 2026-09-30 by live's own code — another shape. */
    private const LEGACY_KINDS = ['ticket_reissue_addition', 'ticket_refund_loss'];

    /** @var array<string, int|null> */
    private array $accounts = [];

    public static function available(): bool
    {
        try {
            return Change::available() && Change::hasFaultColumns();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Why this entry cannot be corrected here, or null when it can. */
    public static function problem(Change $c): ?string
    {
        if ($c->reason === 'undone') {
            return 'This entry was undone — nothing of it is posted any more.';
        }
        if (in_array($c->kind, self::LEGACY_KINDS, true)) {
            return 'Recorded the old way (before 30 Sep 2026) — it cannot be corrected here.';
        }
        if ($c->kind === 'restored') {
            return 'Putting him back on the flight posted nothing.';
        }
        if (Transaction::where('type', Change::PENALTY_LEDGER_TYPE)->where('invoice_id', $c->id)->exists()) {
            return 'Its penalty was billed the old way, on a separate row — correct it with Edit charge.';
        }

        return null;
    }

    /** The entry a correction works on: a per-ticket "we bear" half belongs to the entry it was split from. */
    public function primary(Change $c): Change
    {
        if (str_starts_with((string) $c->note, 'Charge per ticket') && preg_match('/same move as change #(\d+)/', (string) $c->note, $m)) {
            $p = Change::where('contract_flight_passenger_id', $c->contract_flight_passenger_id)->find((int) $m[1]);
            if ($p) {
                return $p;
            }
        }

        return $c;
    }

    // ── what is posted now ──────────────────────────────────────────────

    /**
     * Everything the ledgers hold for this entry (and its halves), read from the postings themselves.
     *
     * @return array{seller: array<string, array<string, float>>, agent: array<int, array<int, array{ledger: float, pen: float, fee: float}>>,
     *               fare_agent: array<int, float>, fare_company: float, penalty_company: float}
     */
    public function posted(Change $p, Collection $halves): array
    {
        $tickets = $this->tickets($p);
        $byPnr = $tickets->mapWithKeys(fn ($t) => [(string) $t->ticket_no => (int) $t->id])->all();
        $firstId = (int) ($tickets->first()?->id ?? 0);
        $feeHead = $this->acc('cf_change_company');
        $fareHead = $this->acc('cf_fare_diff_company');
        $ap = $this->acc('accounts_payable');

        // seller: per ticket and part, per seller — from this entry's seller journals
        $seller = [];
        $entries = JournalEntry::with('items')->where('source', Change::REISSUE_FEE_TYPE)->where('source_id', $p->id)->get();
        $walletOf = PortalBalance::whereIn('journal_entry_id', $entries->pluck('id'))->pluck('portal_id', 'journal_entry_id');
        foreach ($entries as $e) {
            $tid = $this->sellerTicketOf($e, $tickets, $byPnr, $firstId);
            $other = $e->items->first(fn ($i) => ! in_array((int) $i->account_id, [$feeHead, $fareHead], true));
            $t = $tickets->firstWhere('id', $tid);
            $who = $other && (int) $other->account_id === $ap && $other->party_type === 'vendor' && $other->party_id
                ? 'v:'.$other->party_id
                : (isset($walletOf[$e->id]) ? 'p:'.$walletOf[$e->id] : ($t && $t->portal_id && ! $t->vendor_id ? 'p:'.$t->portal_id : 'x'));
            foreach ($e->items as $i) {
                $head = (int) $i->account_id === $feeHead ? 'fee' : ((int) $i->account_id === $fareHead ? 'fare' : null);
                if ($head) {
                    $seller[$tid.':'.$head][$who] = round(($seller[$tid.':'.$head][$who] ?? 0) + (float) $i->debit - (float) $i->credit, 2);
                }
            }
        }

        // agent: his change line and the heads its journals credited — per entry, per agent
        $ids = array_merge([$p->id], $halves->pluck('id')->all());
        $agent = [];
        foreach (Transaction::where('type', Change::LEDGER_TYPE)->whereIn('invoice_id', $ids)
            ->selectRaw('invoice_id, user_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->groupBy('invoice_id', 'user_id')->get() as $r) {
            $agent[(int) $r->invoice_id][(int) $r->user_id] = ['ledger' => round((float) $r->n, 2), 'pen' => 0.0, 'fee' => 0.0];
        }
        $ar = $this->acc('accounts_receivable');
        $penHead = $this->acc('cf_penalty_recovered');
        foreach (JournalEntry::with('items')->whereIn('source', [Change::LEDGER_TYPE, Change::FIX_SOURCE])->whereIn('source_id', $ids)->get() as $e) {
            $arLine = $e->items->first(fn ($i) => (int) $i->account_id === $ar);
            $owner = $p->id === (int) $e->source_id ? $p : ($halves->firstWhere('id', (int) $e->source_id) ?? $p);
            $uid = (int) ($arLine?->party_id ?: $e->beneficiary_party_id ?: $owner->agent_id);
            if (! $uid) {
                continue;
            }
            $agent[(int) $e->source_id][$uid] ??= ['ledger' => 0.0, 'pen' => 0.0, 'fee' => 0.0];
            foreach ($e->items as $i) {
                if ((int) $i->account_id === $ar) {
                    continue;
                }
                $k = (int) $i->account_id === $penHead ? 'pen' : 'fee';
                $agent[(int) $e->source_id][$uid][$k] = round($agent[(int) $e->source_id][$uid][$k] + (float) $i->credit - (float) $i->debit, 2);
            }
        }

        $fareAgent = Transaction::where('type', Change::FARE_LEDGER_TYPE)->where('invoice_id', $p->id)
            ->selectRaw('user_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->groupBy('user_id')->pluck('n', 'user_id')
            ->map(fn ($n) => round((float) $n, 2))->all();

        return [
            'seller' => $seller,
            'agent' => $agent,
            'fare_agent' => $fareAgent,
            'fare_company' => $this->journalNet(Change::FARE_COMPANY_SOURCE, $p->id, $fareHead),
            'penalty_company' => $this->journalNet(Change::PENALTY_COMPANY_SOURCE, $p->id, $this->acc('cf_penalty_company')),
        ];
    }

    /**
     * Which ticket a seller journal belongs to: the PNR in its reference ("PNR-C12") — but a PNR
     * renamed after the posting no longer matches (2026-11-01: two tickets' fees read as one ticket's
     * split), so then the seller it credits decides: the ticket bought from that vendor, or through
     * the portal whose account it credits. The first ticket only when nothing tells.
     */
    public function sellerTicketOf(JournalEntry $e, Collection $tickets, array|Collection $byPnr, int $firstId): int
    {
        $pnr = preg_replace('/-C\d+$/', '', (string) $e->reference);
        if (isset($byPnr[$pnr])) {
            return (int) $byPnr[$pnr];
        }
        $e->loadMissing('items');
        $vendor = (int) ($e->beneficiary_party_id ?: $e->items->firstWhere('party_type', 'vendor')?->party_id);
        $byVendor = $vendor ? $tickets->where('vendor_id', $vendor) : collect();
        if ($byVendor->count() === 1) {
            return (int) $byVendor->first()->id;
        }
        $credited = $e->items->where('credit', '>', 0)->pluck('account_id')->map(fn ($a) => (int) $a)->all();
        $byPortal = $tickets->filter(fn ($t) => ! $t->vendor_id && $t->portal_id
            && in_array((int) Portal::whereKey($t->portal_id)->value('account_id'), $credited, true));
        if ($byPortal->count() === 1) {
            return (int) $byPortal->first()->id;
        }

        return $firstId;
    }

    /** A fingerprint of what is posted: a correction posts only onto the state it was previewed on. */
    public function signature(Change $p, Collection $halves, ?array $posted = null): string
    {
        $rows = collect([$p->fresh()])->merge($halves->map->fresh())->filter()
            ->map(fn ($r) => $r->only(['id', 'charge', 'bearer', 'agent_id', 'fault', 'airline_penalty', 'fare_difference', 'fare_bearer', 'agent_fare_amount', 'reason', 'note']))->values();
        $refunds = $this->tickets($p)->mapWithKeys(fn ($t) => [$t->id => [$t->status, (float) $t->refund_amount]]);

        return md5(json_encode([$posted ?? $this->posted($p, $halves), $rows, $refunds]));
    }

    // ── the dialog ──────────────────────────────────────────────────────

    /** The entry as the dialog shows it: every part with its current value, and the pick lists. */
    public function form(Change $c): array
    {
        $p = $this->primary($c);
        $p->loadMissing(['passenger.passportHolder', 'passenger.agent', 'fromFlight', 'toFlight', 'creator']);
        $halves = $this->halves($p);
        $posted = $this->posted($p, $halves);
        $perTicket = $this->isPerTicket($p);
        $tickets = $this->tickets($p);

        $mine = $this->itemsOf($p->note);
        $ourItems = $halves->flatMap(fn ($h) => array_keys($this->itemsOf($h->note)['items']))->all();
        $rows = [];
        if ($perTicket) {
            foreach ($tickets as $t) {
                $pnr = (string) $t->ticket_no;
                $parts = [];
                foreach (['fee', 'fare'] as $head) {
                    $key = $t->id.':'.$head;
                    $held = array_filter($posted['seller'][$key] ?? [], fn ($v) => abs($v) >= 0.005);
                    // the fare part of a lane Re-Issue is its own seller row; Re-assign posts the fee only
                    if (! $held && $head === 'fare' && $p->kind !== 'ticket_reissue') {
                        continue;
                    }
                    arsort($held);
                    $who = (string) (array_key_first($held) ?: $this->defaultSeller($t));
                    $parts[] = ['key' => $key, 'head' => $head, 'label' => self::PART_LABELS[$head], 'seller' => $who,
                        'seller_label' => $this->sellerName($who), 'amount' => round(array_sum($held), 2), 'split' => count($held) > 1];
                }
                // a "we bear" half's tickets are ours; the entry's own follow its who-pays field — never its
                // note, which the older Edit charge left saying "we bear" after a flip to the agent
                $splitAmount = in_array($pnr, $ourItems, true) && isset($mine['items'][$pnr]) ? (float) $mine['items'][$pnr] : null;
                $bearer = $splitAmount !== null ? 'agent' : (in_array($pnr, $ourItems, true) ? 'company' : ($p->bearer === 'agent' ? 'agent' : ($p->bearer === 'company' ? 'company' : null)));
                $rows[] = ['ticket_id' => (int) $t->id, 'pnr' => $pnr, 'bearer' => $bearer, 'agent_amount' => $splitAmount, 'parts' => $parts];
            }
        }
        $agentNow = collect($posted['agent'])->flatMap(fn ($byAgent) => collect($byAgent)->filter(fn ($a) => abs($a['ledger']) >= 0.005)->keys())->first()
            ?: ($p->agent_id ?: $p->passenger?->agent_id);
        $refunded = $tickets->where('status', 'cancelled')->values();

        return [
            'id' => $p->id,
            'opened' => $c->id,
            'kind' => $p->kind,
            'title' => (Change::KINDS[$p->kind] ?? $p->kind).$this->moveLabel($p),
            'reason_label' => $p->reason ? (Change::CANCEL_REASONS[$p->reason] ?? Change::OFFLOAD_EVENTS[$p->reason] ?? null) : null,
            'passenger' => $p->passenger?->passportHolder?->name,
            'at' => $p->created_at?->format('d M Y, h:i A'),
            'by' => $p->creator?->name,
            'note' => $p->note,
            'halves' => $halves->map(fn ($h) => ['id' => $h->id, 'note' => $h->note])->values()->all(),
            'problem' => self::problem($p),
            'agent_id' => $agentNow ? (int) $agentNow : null,
            'per_ticket' => $perTicket,
            'tickets' => $rows,
            'ours' => $perTicket ? $mine['ours'] : 0.0,
            'charge' => $perTicket ? 0.0 : round((float) ($p->charge ?? 0), 2),
            // a charge of its own is our fee, billed to the agent; an older one we absorbed keeps its pill
            'charge_bearer' => ! $perTicket && (float) $p->charge > 0 ? $p->bearer : null,
            'penalty' => (float) $p->airline_penalty > 0 || $refunded->isNotEmpty() ? [
                'amount' => round((float) $p->airline_penalty, 2),
                'fault' => $p->fault,
                // what the airline kept follows its refund — typed here, posted through Ticketing
                'refunds' => $p->kind === 'flagged' ? [] : $refunded->map(fn ($t) => [
                    'ticket_id' => (int) $t->id, 'pnr' => (string) $t->ticket_no, 'cost' => round((float) $t->amount, 2),
                    'refund' => round((float) $t->refund_amount, 2),
                ])->all(),
            ] : null,
            'fare' => $p->fare_difference !== null && $p->kind !== 'ticket_reissue' ? ['amount' => round((float) $p->fare_difference, 2), 'bearer' => $p->fare_bearer, 'agent_amount' => $p->agent_fare_amount] : null,
            'date' => now()->toDateString(),
            'signature' => $this->signature($p, $halves, $posted),
            'options' => $this->options(array_merge(...array_map(fn ($r) => array_column($r['parts'], 'seller'), $rows ?: [['parts' => []]]))),
        ];
    }

    // ── the plan ────────────────────────────────────────────────────────

    /**
     * What the corrected entry posts, as added rows — nothing written. Each op is posted by
     * execute() from the very lines shown here, so the preview is what posts.
     *
     * @return array{ops: array, preview: array, rows: array, changed: string[], date: string}
     */
    public function plan(Change $c, array $in): array
    {
        $p = $this->primary($c);
        if ($why = self::problem($p)) {
            throw new \InvalidArgumentException($why);
        }
        $p->loadMissing(['passenger.passportHolder', 'passenger.contractFlight.creator', 'fromFlight', 'toFlight']);
        $halves = $this->halves($p);
        $posted = $this->posted($p, $halves);
        $form = $this->form($p);
        $perTicket = $form['per_ticket'];
        $agentId = ! empty($in['agent_id']) ? (int) $in['agent_id'] : null;
        $money = fn ($v) => '৳'.number_format((float) $v, 2);
        $side = fn (?string $b) => $b === 'agent' ? 'Agent pays' : ($b === 'company' ? 'We bear it' : 'nobody');
        $changed = [];
        $fail = function (string $m) {
            throw new \InvalidArgumentException($m);
        };

        // the agent already billed stands even if the user is no longer an agent; a new one must be
        if ($agentId && $agentId !== $form['agent_id'] && ! User::role('agent')->whereKey($agentId)->exists()) {
            $fail('That agent was not found.');
        }

        // 1. the seller's charge, ticket by ticket: its parts, its seller, who bears it
        $parts = [];
        $agentItems = [];
        $ourItems = [];
        if ($perTicket) {
            $given = collect($in['tickets'] ?? [])->keyBy(fn ($t) => (int) ($t['ticket_id'] ?? 0));
            foreach ($form['tickets'] as $row) {
                $g = $given->get($row['ticket_id'], []);
                $gParts = collect($g['parts'] ?? [])->keyBy('key');
                $total = 0.0;
                foreach ($row['parts'] as $part) {
                    $gp = $gParts->get($part['key'], []);
                    $amount = round((float) ($gp['amount'] ?? $part['amount']), 2);
                    $who = (string) ($gp['seller'] ?? $part['seller']);
                    if ($amount < 0) {
                        $fail($part['label'].' on '.$row['pnr'].' cannot be negative.');
                    }
                    // a seller already posted stands as it is; a new one must exist (and an active portal)
                    if ($amount > 0 && ($who === '' || ($who !== $part['seller'] && ! $this->sellerExists($who)))) {
                        $fail('Choose who sold the '.mb_strtolower($part['label']).' on '.$row['pnr'].'.');
                    }
                    if (abs($amount - $part['amount']) >= 0.005) {
                        $changed[] = $part['label'].' '.$row['pnr'].': '.$money($part['amount']).' → '.$money($amount);
                    }
                    if ($amount > 0 && $part['amount'] > 0 && $who !== $part['seller']) {
                        $changed[] = $part['label'].' '.$row['pnr'].': seller '.$part['seller_label'].' → '.$this->sellerName($who);
                    }
                    $parts[] = $part + ['ticket_id' => $row['ticket_id'], 'pnr' => $row['pnr'], 'new_amount' => $amount, 'new_seller' => $who];
                    $total += $amount;
                }
                $total = round($total, 2);
                $bearer = $total > 0 ? ($g['bearer'] ?? $row['bearer']) : null;
                if ($total > 0 && ! in_array($bearer, ['agent', 'company'], true)) {
                    $fail('Say who bears the seller\'s charge on '.$row['pnr'].' — Agent pays or We bear it.');
                }
                if ($total > 0 && $bearer !== $row['bearer']) {
                    $changed[] = $row['pnr'].': '.$side($row['bearer']).' → '.$side($bearer);
                }
                if ($total > 0) {
                    $share = $g['agent_amount'] ?? $row['agent_amount'] ?? null;
                    if ($bearer === 'agent' && $share !== null && $share !== '') {
                        $agentItems[$row['pnr']] = ContractFlightCostSplit::agentAmount($total, 'split', $share, $agentId);
                        $ourItems[$row['pnr']] = round($total - $agentItems[$row['pnr']], 2);
                    } else {
                        $bearer === 'agent' ? $agentItems[$row['pnr']] = $total : $ourItems[$row['pnr']] = $total;
                    }
                }
            }
        }
        $agentPassed = round(array_sum($agentItems), 2);
        $ourPassed = round(array_sum($ourItems), 2);
        $ours = $perTicket ? round(max(0.0, (float) ($in['ours'] ?? $form['ours'])), 2) : 0.0;
        if ($perTicket && abs($ours - $form['ours']) >= 0.005) {
            $changed[] = 'Our charge: '.$money($form['ours']).' → '.$money($ours);
        }

        // 2. a charge of the entry's own (not per ticket): our fee, billed to the agent
        $charge = 0.0;
        $chargeBearer = null;
        if (! $perTicket) {
            $charge = round(max(0.0, (float) ($in['charge'] ?? $form['charge'])), 2);
            $chargeBearer = $charge > 0 ? (($in['charge_bearer'] ?? null) === 'company' && $form['charge_bearer'] === 'company' ? 'company' : 'agent') : null;
            if (abs($charge - $form['charge']) >= 0.005 || ($charge > 0 && $chargeBearer !== $form['charge_bearer'])) {
                $changed[] = 'Our charge: '.$money($form['charge']).($form['charge_bearer'] === 'company' ? ' (we absorbed it)' : '')
                    .' → '.$money($charge).($chargeBearer === 'company' ? ' (we absorb it)' : '');
            }
        }

        // 3. what the airline kept: follows the refunds; whose side it is
        $refundOps = [];
        $penalty = round((float) $p->airline_penalty, 2);
        $fault = $p->fault;
        if ($form['penalty']) {
            foreach ($form['penalty']['refunds'] as $r) {
                $new = $in['refunds'][$r['ticket_id']] ?? null;
                if ($new === null || $new === '') {
                    continue;
                }
                $new = round((float) $new, 2);
                if ($new < 0 || $new > $r['cost'] + 0.005) {
                    $fail('The refund on '.$r['pnr'].' must be between ৳0 and its cost '.$money($r['cost']).'.');
                }
                if (abs($new - $r['refund']) >= 0.005) {
                    $refundOps[] = ['op' => 'refund', 'ticket_id' => $r['ticket_id'], 'pnr' => $r['pnr'], 'old' => $r['refund'], 'new' => $new];
                    $penalty = round(max(0.0, $penalty - ($new - $r['refund'])), 2);
                    $changed[] = 'Airline refund '.$r['pnr'].': '.$money($r['refund']).' → '.$money($new);
                }
            }
            $fault = $penalty > 0 ? ($in['fault'] ?? $p->fault) : null;
            if ($penalty > 0 && ! in_array($fault, ['agent', 'company'], true)) {
                $fail('The airline keeps '.$money($penalty).' — say whose side it is.');
            }
            if ($penalty > 0 && $fault !== $p->fault) {
                $changed[] = 'Airline kept '.$money($penalty).': '.$side($p->fault).' → '.$side($fault);
            }
        }

        // 4. a move's fare difference (inside the new ticket's price; only its bearer and figure)
        $fare = $p->fare_difference !== null ? round((float) $p->fare_difference, 2) : null;
        $fareBearer = $p->fare_bearer;
        if ($form['fare']) {
            $fare = round((float) ($in['fare'] ?? $fare), 2);
            $fareBearer = abs($fare) >= 0.005 ? ($in['fare_bearer'] ?? $p->fare_bearer) : $p->fare_bearer;
            if (abs($fare) >= 0.005 && ! in_array($fareBearer, ['agent', 'company'], true)) {
                $fail('Say who bears the fare difference — Agent pays or We bear it.');
            }
            if (abs($fare - (float) $p->fare_difference) >= 0.005) {
                $changed[] = 'Fare difference: '.$money($p->fare_difference).' → '.$money($fare);
            }
            if (abs($fare) >= 0.005 && $fareBearer !== $p->fare_bearer) {
                $changed[] = 'Fare difference: '.$side($p->fare_bearer).' → '.$side($fareBearer);
            }
        }

        // who is billed, and for what — ONE line: penalties he bears + our charge
        $agentPen = round($agentPassed + ($fault === 'agent' ? $penalty : 0), 2);
        $agentFee = round($perTicket ? $ours : ($chargeBearer === 'agent' ? $charge : 0), 2);
        $agentFareShare = $p->agent_fare_amount;
        if (array_key_exists('agent_fare_amount', $in)) {
            $agentFareShare = $in['agent_fare_amount'] === '' || $in['agent_fare_amount'] === null ? null : round((float) $in['agent_fare_amount'], 2);
        }
        if ($fareBearer !== 'agent') {
            $agentFareShare = null;
        }
        if ($agentFareShare !== null && ((float) $fare <= 0 || $agentFareShare < 0 || $agentFareShare > (float) $fare)) {
            $fail('The agent share must be between zero and the positive fare difference.');
        }
        if ($agentFareShare !== $p->agent_fare_amount && (float) $agentFareShare !== (float) $p->agent_fare_amount) {
            $changed[] = 'Fare difference agent share: '.$money($p->agentFareAmount()).' to '.$money($agentFareShare ?? ($fareBearer === 'agent' ? $fare : 0));
        }
        $agentFare = $form['fare'] && $fareBearer === 'agent' ? (float) ($agentFareShare ?? $fare) : 0.0;
        if (($agentPen + $agentFee > 0 || abs($agentFare) >= 0.005) && ! $agentId) {
            $fail('Choose the agent who is billed.');
        }
        $billedBefore = collect($posted['agent'])->contains(fn ($byAgent) => collect($byAgent)->contains(fn ($a) => abs($a['ledger']) >= 0.005));
        if ($agentId !== $form['agent_id'] && ($agentPen + $agentFee > 0 || abs($agentFare) >= 0.005 || $billedBefore)) {
            $changed[] = 'Agent billed: '.($form['agent_id'] ? User::whereKey($form['agent_id'])->value('name') : 'nobody').' → '.($agentId ? User::whereKey($agentId)->value('name') : 'nobody');
        }

        // ── the differences, as ops ──
        $ops = $refundOps;
        if ($refundOps && abs($penalty - (float) $p->airline_penalty) >= 0.005) {
            $ops[] = ['op' => 'penalty', 'delta' => round($penalty - (float) $p->airline_penalty, 2)];
        }
        foreach ($parts as $part) {
            $held = $posted['seller'][$part['key']] ?? [];
            $sellers = array_unique(array_merge(array_keys($held), $part['new_amount'] > 0 ? [$part['new_seller']] : []));
            foreach ($sellers as $who) {
                $delta = round(($who === $part['new_seller'] ? $part['new_amount'] : 0.0) - (float) ($held[$who] ?? 0), 2);
                if (abs($delta) < 0.005) {
                    continue;
                }
                if ($who === 'x') {
                    $fail('The '.mb_strtolower($part['label']).' on '.$part['pnr'].' was posted with no seller named — it cannot be moved here.');
                }
                $ops[] = ['op' => 'seller', 'ticket_id' => $part['ticket_id'], 'pnr' => $part['pnr'], 'head' => $part['head'], 'seller' => $who, 'delta' => $delta];
            }
        }
        // the agent's line sits on the entry itself; anything on a half or on another agent goes to 0
        $holders = [$p->id => [$agentId => true]];
        foreach ($posted['agent'] as $cid => $byAgent) {
            foreach (array_keys($byAgent) as $uid) {
                $holders[$cid][$uid] = true;
            }
        }
        foreach ($holders as $cid => $uids) {
            foreach (array_keys($uids) as $uid) {
                if (! $uid) {
                    continue;
                }
                $held = $posted['agent'][$cid][$uid] ?? ['ledger' => 0.0, 'pen' => 0.0, 'fee' => 0.0];
                $mine = (int) $cid === $p->id && (int) $uid === $agentId;
                $dPen = round(($mine ? $agentPen : 0) - $held['pen'], 2);
                $dFee = round(($mine ? $agentFee : 0) - $held['fee'], 2);
                $dLine = round(($mine ? $agentPen + $agentFee : 0) - $held['ledger'], 2);
                if (abs($dPen) >= 0.005 || abs($dFee) >= 0.005 || abs($dLine) >= 0.005) {
                    $ops[] = ['op' => 'agent', 'change_id' => (int) $cid, 'agent_id' => (int) $uid, 'pen' => $dPen, 'fee' => $dFee, 'line' => $dLine,
                        'was' => $held['ledger'], 'now' => round($held['ledger'] + $dLine, 2)];
                }
            }
        }
        if ($form['fare']) {
            $dCo = round(max(0.0, (float) $fare) - $posted['fare_company'], 2);
            if (abs($dCo) >= 0.005) {
                $ops[] = ['op' => 'fare_company', 'delta' => $dCo];
            }
            foreach (array_unique(array_merge(array_keys($posted['fare_agent']), $agentId ? [$agentId] : [])) as $uid) {
                $d = round(((int) $uid === $agentId ? $agentFare : 0) - (float) ($posted['fare_agent'][$uid] ?? 0), 2);
                if (abs($d) >= 0.005) {
                    $ops[] = ['op' => 'fare_agent', 'agent_id' => (int) $uid, 'delta' => $d];
                }
            }
        }

        // the entry (and its "we bear" half) as they will read afterwards
        if ($perTicket) {
            $agentSide = $agentPassed + $ours > 0;
            $entry = [
                'charge' => $agentSide ? round($agentPassed + $ours, 2) : $ourPassed,
                'bearer' => $agentSide ? 'agent' : ($ourPassed > 0 ? 'company' : null),
                'items' => $agentSide ? $agentItems + ($ours > 0 ? ['our charge' => $ours] : []) : $ourItems,
            ];
            $half = $agentSide && $ourPassed > 0 ? ['charge' => $ourPassed, 'items' => $ourItems] : null;
        } else {
            $entry = ['charge' => $charge, 'bearer' => $chargeBearer, 'items' => null];
            $half = null;
        }
        $rows = [
            'entry' => $entry + [
                'agent_id' => $agentId,
                'fault' => $penalty > 0 ? $fault : null,
                'airline_penalty' => $penalty > 0 ? $penalty : null,
                'fare_difference' => $fare,
                'fare_bearer' => $fare !== null ? $fareBearer : $p->fare_bearer,
                ...(Change::splitFareAvailable() ? ['agent_fare_amount' => $agentFareShare] : []),
                'fee_part' => collect($parts)->where('head', 'fee')->sum('new_amount'),
                'fare_part' => collect($parts)->where('head', 'fare')->sum('new_amount'),
            ],
            'half' => $half,
        ];
        $date = $this->date($in['date'] ?? null);
        $reason = trim((string) ($in['reason'] ?? ''));

        return [
            'id' => $p->id,
            'ops' => $ops,
            'changed' => array_values(array_unique($changed)),
            'rows' => $rows,
            'date' => $date,
            'preview' => array_map(fn ($op) => $this->lines($p, $op, $date) + ['kind' => $op['op']], $ops),
            'rows_change' => $this->rowsDiffer($p, $halves, $rows),
            'reason' => $reason,
        ];
    }

    /** Post the plan in one transaction; returns what was posted. */
    public function apply(Change $c, array $in): array
    {
        $reason = trim((string) ($in['reason'] ?? ''));
        if ($reason === '') {
            throw new \InvalidArgumentException('Write why it is corrected — it is kept with the entry.');
        }

        return DB::transaction(function () use ($c, $in, $reason) {
            $p = $this->primary($c);
            $p = Change::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $halves = $this->halves($p);
            if (($in['signature'] ?? '') !== $this->signature($p, $halves)) {
                throw new \InvalidArgumentException('This entry changed since the preview (or was just corrected). Close it, open it again and check before posting.');
            }
            $plan = $this->plan($p, $in);
            if (! $plan['ops'] && ! $plan['rows_change']) {
                throw new \InvalidArgumentException('Nothing to correct — the entry already says this.');
            }
            foreach ($plan['ops'] as $op) {
                $this->execute($p, $op, $plan['date'], $reason);
            }
            $this->writeRows($p, $halves, $plan['rows'], $plan['date'], $reason);
            if ($pax = $p->passenger) {
                $pax->events()->create([
                    'type' => 'finance', 'code' => self::EVENT_CODE, 'label' => 'Entry corrected',
                    'note' => mb_substr((Change::KINDS[$p->kind] ?? $p->kind).' · '.implode(' · ', $plan['changed'] ?: ['entry restated']).' · '.$reason, 0, 1000),
                    'occurred_at' => now(), 'created_by' => Auth::id(),
                ]);
            }

            return $plan;
        });
    }

    // ── one difference: its rows, for the preview and for posting ───────

    /**
     * The party row and journal one op adds, in words and figures (account codes and names).
     *
     * @return array{title: string, party: ?array, journal: ?array}
     */
    private function lines(Change $p, array $op, string $date): array
    {
        $name = $p->passenger?->passportHolder?->name ?: 'passenger';
        $named = fn (?int $id) => $id ? ['code' => Account::whereKey($id)->value('code'), 'name' => Account::whereKey($id)->value('name'), 'id' => $id] : ['code' => '—', 'name' => 'missing head', 'id' => null];
        $head = fn (string $key) => $named($this->acc($key));
        $line = fn (array $a, float $dr, float $cr) => $a + ['dr' => round($dr, 2), 'cr' => round($cr, 2)];
        $on = Carbon::parse($date)->format('d M Y');
        $abs = fn ($v) => '৳'.number_format(abs((float) $v), 2);

        switch ($op['op']) {
            case 'seller':
                $d = $op['delta'];
                [$kind, $sid] = explode(':', $op['seller']) + [1 => null];
                $portal = $kind === 'p' ? Portal::find((int) $sid) : null;
                $other = $kind === 'v' ? $head('accounts_payable') : $named($portal?->account_id ? (int) $portal->account_id : null);
                $part = $head($op['head'] === 'fee' ? 'cf_change_company' : 'cf_fare_diff_company');
                $text = self::PART_LABELS[$op['head']].' corrected — '.$op['pnr'].' · '.$name.' · '.($d > 0 ? 'owed ' : 'no longer owed ').$abs($d);

                return [
                    'title' => self::PART_LABELS[$op['head']].' · '.$op['pnr'],
                    // a vendor row is a Bill when we owe more (debit, as the first posting); a wallet pays out (credit)
                    'party' => ['ledger' => $kind === 'v' ? 'Vendor' : 'Portal wallet', 'name' => $this->sellerName($op['seller']),
                        'dr' => $kind === 'v' ? max(0, $d) : max(0, -$d), 'cr' => $kind === 'v' ? max(0, -$d) : max(0, $d),
                        'text' => $text, 'date' => $on],
                    'journal' => ['lines' => $d > 0 ? [$line($part, $d, 0), $line($other, 0, $d)] : [$line($other, -$d, 0), $line($part, 0, -$d)], 'date' => $on],
                ];

            case 'agent':
                $sum = round($op['pen'] + $op['fee'], 2);
                $lines = [];
                if (abs($sum) >= 0.005) {
                    $lines[] = $line($head('accounts_receivable'), max(0, $sum), max(0, -$sum));
                }
                if (abs($op['pen']) >= 0.005) {
                    $lines[] = $line($head('cf_penalty_recovered'), max(0, -$op['pen']), max(0, $op['pen']));
                }
                if (abs($op['fee']) >= 0.005) {
                    $lines[] = $line($head('cf_change_recovered'), max(0, -$op['fee']), max(0, $op['fee']));
                }
                $d = $op['line'];
                $agent = User::whereKey($op['agent_id'])->value('name');

                return [
                    'title' => 'Agent line · '.$agent,
                    'party' => abs($d) >= 0.005 ? ['ledger' => 'Agent', 'name' => $agent, 'dr' => max(0, $d), 'cr' => max(0, -$d),
                        'text' => $this->agentText($p, $op), 'date' => $on] : null,
                    'journal' => $lines ? ['lines' => $lines, 'date' => $on] : null,
                ];

            case 'refund':
                $t = TicketPurchase::find($op['ticket_id']);
                $d = round($op['new'] - $op['old'], 2);
                $portal = $t && ! $t->vendor_id && $t->portal_id ? Portal::find($t->portal_id) : null;
                $other = $portal ? $named($portal->account_id ? (int) $portal->account_id : null) : $head('accounts_payable');

                return [
                    'title' => 'Airline refund · '.$op['pnr'].' ৳'.number_format($op['old'], 2).' → ৳'.number_format($op['new'], 2),
                    'party' => ['ledger' => $portal ? 'Portal wallet' : 'Vendor', 'name' => $portal ? $portal->name : (string) User::whereKey($t?->vendor_id)->value('name'),
                        'dr' => $portal ? max(0, $d) : max(0, -$d), 'cr' => $portal ? max(0, -$d) : max(0, $d),
                        'text' => 'Refund corrected — '.$op['pnr'].' · '.$name, 'date' => $on],
                    'journal' => ['lines' => $d > 0 ? [$line($other, $d, 0), $line($head('ticket_purchase_cost'), 0, $d)]
                        : [$line($head('ticket_purchase_cost'), -$d, 0), $line($other, 0, -$d)], 'date' => $on],
                ];

            case 'penalty':
                $d = $op['delta'];

                return [
                    'title' => 'What the airline kept — Company Ledger',
                    'party' => null,
                    'journal' => ['lines' => $d > 0 ? [$line($head('cf_penalty_company'), $d, 0), $line($head('ticket_purchase_cost'), 0, $d)]
                        : [$line($head('ticket_purchase_cost'), -$d, 0), $line($head('cf_penalty_company'), 0, -$d)], 'date' => $on],
                ];

            case 'fare_company':
                $d = $op['delta'];

                return [
                    'title' => 'Fare difference — Company Ledger',
                    'party' => null,
                    'journal' => ['lines' => $d > 0 ? [$line($head('cf_fare_diff_company'), $d, 0), $line($head('ticket_purchase_cost'), 0, $d)]
                        : [$line($head('ticket_purchase_cost'), -$d, 0), $line($head('cf_fare_diff_company'), 0, -$d)], 'date' => $on],
                ];

            case 'fare_agent':
                $d = $op['delta'];
                $agent = User::whereKey($op['agent_id'])->value('name');

                return [
                    'title' => 'Fare difference · '.$agent,
                    'party' => ['ledger' => 'Agent', 'name' => $agent, 'dr' => max(0, $d), 'cr' => max(0, -$d),
                        'text' => 'Fare difference corrected — '.$name, 'date' => $on],
                    'journal' => ['lines' => $d > 0 ? [$line($head('accounts_receivable'), $d, 0), $line($head('cf_fare_diff_recovered'), 0, $d)]
                        : [$line($head('cf_fare_diff_recovered'), -$d, 0), $line($head('accounts_receivable'), 0, -$d)], 'date' => $on],
                ];
        }

        return ['title' => '', 'party' => null, 'journal' => null];
    }

    /** Post one op: its party row (vendor, portal wallet or agent) and its balanced journal — the lines shown. */
    private function execute(Change $p, array $op, string $date, string $reason): void
    {
        $name = $p->passenger?->passportHolder?->name ?: 'passenger';
        $flightNo = $p->toFlight?->flight_number ?: $p->fromFlight?->flight_number ?: 'CF';
        $companyId = TravelCompany::ID;
        $ref = $flightNo.'-'.$p->contract_flight_passenger_id.'-C'.($op['change_id'] ?? $p->id);
        $shown = $this->lines($p, $op, $date);
        $why = ' · '.$reason;
        $ids = fn (array $lines, ?string $partyType = null, ?int $partyId = null, ?int $partyAcct = null) => array_map(
            fn ($l) => [$l['id'], $l['dr'], $l['cr'], $partyAcct && $l['id'] === $partyAcct ? $partyType : null, $partyAcct && $l['id'] === $partyAcct ? $partyId : null],
            $lines
        );

        switch ($op['op']) {
            case 'refund':
                $t = TicketPurchase::find($op['ticket_id']);
                $done = $t ? app(TicketPurchaseController::class)->correctRefundAmount($t, $op['new'], 'correction of '.$name.'\'s entry'.$why, $date) : null;
                if ($done === null) {
                    throw new \InvalidArgumentException('The refund on '.$op['pnr'].' cannot be corrected here (its seller posting is not the usual shape) — nothing was posted.');
                }

                return;

            case 'seller':
                [$kind, $sid] = explode(':', $op['seller']);
                $d = $op['delta'];
                $text = $shown['party']['text'];
                $type = $op['head'] === 'fee' ? Change::REISSUE_FEE_TYPE : Change::REISSUE_FARE_TYPE;
                $entry = $this->journal($companyId, $date, $op['pnr'].'-C'.$p->id, Change::REISSUE_FEE_TYPE, $p->id, $text.$why, $kind === 'v' ? (int) $sid : null);
                if ($kind === 'v') {
                    $this->postPartyLedgerRow((int) $sid, ['type' => $type, 'invoice_id' => (int) $op['ticket_id'], 'account_id' => null,
                        'payment_date' => $date, 'reference_no' => $op['pnr'], 'debit' => max(0, $d), 'credit' => max(0, -$d),
                        'remarks' => mb_substr($text, 0, 250), 'company_id' => $companyId]);
                    $this->items($entry, $ids($shown['journal']['lines'], 'vendor', (int) $sid, $this->acc('accounts_payable')), $text.$why);
                } else {
                    $portal = Portal::whereKey((int) $sid)->lockForUpdate()->first();
                    if (! $portal || ! $portal->account_id) {
                        throw new \InvalidArgumentException('Portal '.$this->sellerName($op['seller']).' has no account — nothing was posted.');
                    }
                    $opening = (float) $portal->balance;
                    // linked to its journal: that is how a later correction knows which portal it hit
                    PortalBalance::create(['invoice_id' => (int) $op['ticket_id'], 'portal_id' => $portal->id, 'type' => $type, 'date' => $date,
                        'journal_entry_id' => $entry->id, 'old_balance' => $opening, 'debit' => max(0, -$d), 'credit' => max(0, $d),
                        'current_balance' => $opening - $d, 'remarks' => mb_substr($text, 0, 250), 'created_by' => Auth::id()]);
                    $portal->update(['balance' => $opening - $d]);
                    $this->items($entry, $ids($shown['journal']['lines']), $text.$why);
                }

                return;

            case 'agent':
                $d = $op['line'];
                $text = $this->agentText($p, $op);
                if (abs($d) >= 0.005) {
                    $this->postPartyLedgerRow($op['agent_id'], ['type' => Change::LEDGER_TYPE, 'invoice_id' => $op['change_id'], 'account_id' => null,
                        'payment_date' => $date, 'reference_no' => $ref, 'debit' => max(0, $d), 'credit' => max(0, -$d),
                        'remarks' => mb_substr($text, 0, 250), 'company_id' => $companyId]);
                }
                if ($shown['journal']) {
                    $entry = $this->journal($companyId, $date, $ref, Change::FIX_SOURCE, $op['change_id'], $text.$why, $op['agent_id']);
                    $this->items($entry, $ids($shown['journal']['lines'], 'agent', $op['agent_id'], $this->acc('accounts_receivable')), $text.$why);
                }

                return;

            case 'penalty':
            case 'fare_company':
                $source = $op['op'] === 'penalty' ? Change::PENALTY_COMPANY_SOURCE : Change::FARE_COMPANY_SOURCE;
                $text = ($op['op'] === 'penalty' ? 'Airline kept corrected — ' : 'Fare difference corrected — ').$name.' ('.$flightNo.')';
                $entry = $this->journal($companyId, $date, $ref, $source, $p->id, $text.$why, null);
                $this->items($entry, $ids($shown['journal']['lines']), $text.$why);

                return;

            case 'fare_agent':
                $d = $op['delta'];
                $text = 'Fare difference corrected — '.$name.' ('.$flightNo.')';
                $row = $this->postPartyLedgerRow($op['agent_id'], ['type' => Change::FARE_LEDGER_TYPE, 'invoice_id' => $p->id, 'account_id' => null,
                    'payment_date' => $date, 'reference_no' => $ref, 'debit' => max(0, $d), 'credit' => max(0, -$d),
                    'remarks' => mb_substr($text, 0, 250), 'company_id' => $companyId]);
                // keyed as postAgentAdjustment() keys it: source FARE_LEDGER_TYPE, source_id = the row
                $entry = $this->journal($companyId, $date, $ref, Change::FARE_LEDGER_TYPE, (int) $row->id, $text.$why, $op['agent_id']);
                $this->items($entry, $ids($shown['journal']['lines'], 'agent', $op['agent_id'], $this->acc('accounts_receivable')), $text.$why);

                return;
        }
    }

    // ── the entry's own rows ────────────────────────────────────────────

    /** The entry and its "we bear" half set to what the ledgers now hold; the correction written on the entry. */
    private function writeRows(Change $p, Collection $halves, array $rows, string $date, string $reason): void
    {
        // Party Statement shows the agent every note segment WITHOUT a ৳ figure (owner, 2026-10-13), so the
        // stamp carries one: staff read why and what moved; the agent never sees our reason.
        $line = $this->lineTotals($p, $halves);
        $stamp = 'Corrected '.Carbon::parse($date)->format('d M Y').' by '.(Auth::user()?->name ?: 'admin')
            .' (agent line ৳'.number_format($line, 2).') — '.$reason;
        $this->restate($p, $halves, $rows, $stamp);
    }

    /**
     * The entry (and its "we bear" half) set to the corrected figures. With a stamp (Correct) the
     * stamp is added and a half no longer needed is closed as history; without one (the Trail,
     * which REPLACES the entry — owner, 2026-11-01) earlier "Corrected …" stamps go, since the
     * rows they described are folded into the entry, and a spare half is deleted. Returns the
     * attributes of every half deleted, for the Trail's log.
     */
    public function restate(Change $p, Collection $halves, array $rows, ?string $stamp): array
    {
        $e = $rows['entry'];
        $segs = array_values(array_filter(explode(' · ', (string) $p->note), fn ($s) => $s !== ''
            && ($stamp !== null || ! str_starts_with($s, 'Corrected '))));
        if ($e['items'] !== null) {
            $found = false;
            foreach ($segs as $i => $s) {
                if (str_starts_with($s, 'Charge per ticket')) {
                    $segs[$i] = $this->chargeSegment($e['bearer'], $e['items']);
                    $found = true;
                } elseif (str_starts_with($s, 'airline penalty ৳')) {
                    $segs[$i] = 'airline penalty ৳'.number_format((float) $e['fee_part'], 2).' + fare difference ৳'.number_format((float) $e['fare_part'], 2);
                }
            }
            if (! $found) {
                array_unshift($segs, $this->chargeSegment($e['bearer'], $e['items']));
            }
        }
        if ($stamp !== null) {
            $segs[] = $stamp;
        }
        $p->update([
            'charge' => $e['charge'] > 0 ? $e['charge'] : null,
            'bearer' => $e['charge'] > 0 ? $e['bearer'] : null,
            'agent_id' => $e['agent_id'] ?: $p->agent_id,
            'fault' => $e['fault'],
            'airline_penalty' => $e['airline_penalty'],
            'fare_difference' => $e['fare_difference'],
            'fare_bearer' => $e['fare_bearer'],
            ...(Change::splitFareAvailable() ? ['agent_fare_amount' => $e['agent_fare_amount'] ?? null] : []),
            'note' => mb_substr(implode(' · ', $segs), 0, 2000),
        ]);

        // Our tickets' share beside it — posts nothing (the seller's rows are on the entry); every reader
        // counts it as borne. One half at most; a half no longer needed is closed, kept as history.
        $half = $rows['half'];
        $first = $halves->first();
        if ($half) {
            $attrs = ['charge' => $half['charge'], 'bearer' => 'company', 'agent_id' => $e['agent_id'] ?: $p->agent_id,
                'note' => mb_substr($this->chargeSegment('company', $half['items']).' · same move as change #'.$p->id.($stamp !== null ? ' · '.$stamp : ''), 0, 2000)];
            $first ? $first->update($attrs) : Change::create($attrs + [
                'contract_flight_passenger_id' => $p->contract_flight_passenger_id, 'kind' => $p->kind,
                'from_flight_id' => $p->from_flight_id, 'to_flight_id' => $p->to_flight_id,
                'previous_ticket_purchase_ids' => $p->previous_ticket_purchase_ids, 'created_by' => Auth::id(),
            ]);
        }
        $deleted = [];
        foreach ($half ? $halves->slice(1) : $halves as $h) {
            if ($stamp === null) {
                $deleted[] = $h->getAttributes();
                $h->delete();

                continue;
            }
            $h->update(['charge' => null, 'bearer' => null, 'reason' => 'undone',
                'note' => mb_substr($h->note.' · merged back into change #'.$p->id.' — '.$stamp, 0, 2000)]);
        }

        return $deleted;
    }

    /** The agent line of the entry and its halves as it stands now (after this correction's rows). */
    private function lineTotals(Change $p, Collection $halves): float
    {
        return round((float) Transaction::where('type', Change::LEDGER_TYPE)->whereIn('invoice_id', array_merge([$p->id], $halves->pluck('id')->all()))
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->value('n'), 2);
    }

    private function chargeSegment(?string $bearer, array $items): string
    {
        $list = [];
        foreach ($items as $label => $amount) {
            $list[] = $label.' ৳'.number_format((float) $amount, 2);
        }

        return 'Charge per ticket — '.($bearer === 'agent' ? 'agent pays' : 'we bear').': '.($list ? implode(' + ', $list) : '৳0.00');
    }

    private function rowsDiffer(Change $p, Collection $halves, array $rows): bool
    {
        $e = $rows['entry'];
        if (abs((float) $p->charge - (float) $e['charge']) >= 0.005 || ($e['charge'] > 0 && $p->bearer !== $e['bearer'])
            || ($e['agent_id'] && (int) $p->agent_id !== (int) $e['agent_id']) || $p->fault !== $e['fault']
            || abs((float) $p->airline_penalty - (float) $e['airline_penalty']) >= 0.005
            || abs((float) $p->fare_difference - (float) $e['fare_difference']) >= 0.005 || $p->fare_bearer !== $e['fare_bearer']
            || (float) $p->agent_fare_amount !== (float) ($e['agent_fare_amount'] ?? null)) {
            return true;
        }
        $half = $rows['half'];

        return $half ? (! $halves->first() || abs((float) $halves->first()->charge - $half['charge']) >= 0.005) : $halves->isNotEmpty();
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** Its per-ticket "we bear" halves (split off by Re-assign, Edit charge or a correction), oldest first. */
    public function halves(Change $p): Collection
    {
        return Change::where('contract_flight_passenger_id', $p->contract_flight_passenger_id)->where('id', '!=', $p->id)
            ->where('note', 'like', 'Charge per ticket%')->where('note', 'like', '%same move as change #'.$p->id.'%')
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'undone'))->orderBy('id')->get()
            ->filter(fn ($h) => preg_match('/same move as change #'.$p->id.'(?!\d)/', (string) $h->note))->values();
    }

    /**
     * "Charge per ticket — agent pays: J6M366 ৳11,747.00 + our charge ৳500.00" → its bearer, PNR amounts, our charge.
     *
     * @return array{bearer: ?string, items: array<string, float>, ours: float}
     */
    private function itemsOf(?string $note): array
    {
        if (! preg_match('/Charge per ticket — (agent pays|we bear):\s*(.+?)(?:\s·\s|\s\(same move|$)/u', (string) $note, $m)) {
            return ['bearer' => null, 'items' => [], 'ours' => 0.0];
        }
        $items = [];
        $ours = 0.0;
        foreach (explode(' + ', $m[2]) as $bit) {
            if (preg_match('/^(.+?)\s৳([\d,]+(?:\.\d+)?)$/u', trim($bit), $b)) {
                $amount = (float) str_replace(',', '', $b[2]);
                strcasecmp(trim($b[1]), 'our charge') === 0 ? $ours += $amount : $items[trim($b[1])] = ($items[trim($b[1])] ?? 0) + $amount;
            }
        }

        return ['bearer' => $m[1] === 'agent pays' ? 'agent' : 'company', 'items' => $items, 'ours' => round($ours, 2)];
    }

    private function journal(int $companyId, string $date, string $ref, string $source, int $sourceId, string $text, ?int $partyId): JournalEntry
    {
        return JournalEntry::create([
            'company_id' => $companyId, 'created_by' => Auth::id(), 'date' => $date, 'entry_time' => now()->format('H:i:s'),
            'reference' => mb_substr($ref, 0, 190), 'source' => $source, 'source_id' => $sourceId,
            'description' => mb_substr($text, 0, 250),
            'beneficiary' => $partyId ? User::whereKey($partyId)->value('name') : null, 'beneficiary_party_id' => $partyId,
        ]);
    }

    /** Lines [account id, debit, credit, party type, party id]; refuses an unbalanced or headless journal. */
    private function items(JournalEntry $entry, array $lines, string $note): void
    {
        $lines = array_values(array_filter($lines, fn ($l) => abs((float) $l[1]) >= 0.005 || abs((float) $l[2]) >= 0.005));
        if (! $lines || array_filter($lines, fn ($l) => ! $l[0])) {
            throw new \InvalidArgumentException('An account head is missing on this server — nothing was posted.');
        }
        if (abs(array_sum(array_column($lines, 1)) - array_sum(array_column($lines, 2))) >= 0.005) {
            throw new \InvalidArgumentException('The correction did not balance — nothing was posted.');
        }
        foreach ($lines as [$acct, $dr, $cr, $pt, $pid]) {
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $acct, 'debit' => round((float) $dr, 2), 'credit' => round((float) $cr, 2),
                'note' => mb_substr($note, 0, 250), 'party_type' => $pt, 'party_id' => $pid]);
        }
    }

    /**
     * What the agent's statement says: the penalty and the ONE figure it now comes to — no split
     * (owner, 2026-10-13: penalty and our charge are one penalty line to him), no internal reason.
     */
    private function agentText(Change $p, array $op): string
    {
        $label = match ($p->kind) {
            'cancelled' => 'Cancellation penalty',
            'flagged' => isset(Change::OFFLOAD_EVENTS[$p->reason]) ? Change::OFFLOAD_EVENTS[$p->reason].' charge' : 'Cancellation penalty',
            'reissue', 'ticket_reissue' => 'Reissue penalty', 'ticket_refund' => 'Refund penalty', 'return_cancel' => 'Return cancellation penalty',
            default => 'Reschedule penalty',
        };

        return $label.' — '.($p->passenger?->passportHolder?->name ?: 'passenger').$this->moveLabel($p, ' (', ')')
            .' — corrected to ৳'.number_format((float) $op['now'], 2).' (was ৳'.number_format((float) $op['was'], 2).')';
    }

    private function tickets(Change $c): Collection
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? []))));

        return $ids ? TicketPurchase::whereIn('id', $ids)->orderBy('id')->get(['id', 'ticket_no', 'vendor_id', 'portal_id', 'amount', 'refund_amount', 'status']) : collect();
    }

    private function isPerTicket(Change $c): bool
    {
        return str_contains((string) $c->note, 'Charge per ticket')
            || JournalEntry::where('source', Change::REISSUE_FEE_TYPE)->where('source_id', $c->id)->exists();
    }

    private function moveLabel(Change $c, string $open = ' · ', string $close = ''): string
    {
        $from = $c->fromFlight?->flight_number;
        $to = $c->toFlight?->flight_number;
        $move = $from && $to && $from !== $to ? $from.' → '.$to : ($from ?: $to);

        return $move ? $open.$move.$close : '';
    }

    private function journalNet(string $source, int $id, ?int $account): float
    {
        if (! $account) {
            return 0.0;
        }

        return round((float) DB::table('journal_items as i')->join('journal_entries as e', 'e.id', '=', 'i.journal_entry_id')
            ->where('e.source', $source)->where('e.source_id', $id)->whereNull('e.deleted_at')->whereNull('i.deleted_at')
            ->where('i.account_id', $account)->selectRaw('COALESCE(SUM(i.debit),0) - COALESCE(SUM(i.credit),0) n')->value('n'), 2);
    }

    private function acc(string $key): ?int
    {
        if (! array_key_exists($key, $this->accounts)) {
            $id = Account::where('code', config('accounts.'.$key))->value('id');
            $this->accounts[$key] = $id ? (int) $id : null;
        }

        return $this->accounts[$key];
    }

    private function date(?string $date): string
    {
        try {
            $d = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('The date is not a date.');
        }
        if ($d->gt(now()->endOfDay())) {
            throw new \InvalidArgumentException('A correction cannot be dated after today.');
        }

        return $d->toDateString();
    }

    private function defaultSeller(TicketPurchase $t): string
    {
        return $t->vendor_id ? 'v:'.$t->vendor_id : ($t->portal_id ? 'p:'.$t->portal_id : '');
    }

    private function sellerExists(string $who): bool
    {
        [$kind, $id] = explode(':', $who.':') + [1 => null];

        return match ($kind) {
            'v' => User::role('vendor')->whereKey((int) $id)->exists(),
            'p' => Portal::whereKey((int) $id)->whereNotNull('account_id')->where('status', 'active')->exists(),
            default => false,
        };
    }

    private function sellerName(string $who): string
    {
        [$kind, $id] = explode(':', $who.':') + [1 => null];

        return match ($kind) {
            'v' => (string) (User::whereKey((int) $id)->value('name') ?: 'vendor #'.$id),
            'p' => (string) (Portal::whereKey((int) $id)->value('name') ?: 'portal #'.$id).' (portal)',
            default => 'no seller named',
        };
    }

    /**
     * Agents and sellers to pick from; a seller already posted is always in the list, even inactive.
     *
     * @param  string[]  $current
     * @return array{agents: array, sellers: array}
     */
    private function options(array $current = []): array
    {
        $sellers = array_merge(
            User::role('vendor')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => 'v:'.$u->id, 'name' => $u->name])->all(),
            Portal::whereNotNull('account_id')->where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => 'p:'.$p->id, 'name' => $p->name.' (portal)'])->all()
        );
        $listed = array_column($sellers, 'id');
        foreach (array_unique(array_filter($current)) as $who) {
            if (! in_array($who, $listed, true) && $who !== 'x') {
                $sellers[] = ['id' => $who, 'name' => $this->sellerName($who)];
            }
        }

        return [
            'agents' => User::role('agent')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->all(),
            'sellers' => $sellers,
        ];
    }
}
