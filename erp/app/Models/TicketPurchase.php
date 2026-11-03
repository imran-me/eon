<?php

namespace App\Models;

use App\Services\FlightPackageTicketBilling;
use App\Services\TicketBaggage;
use App\Services\TicketTrail\EditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Added by a guarded migration (2026_10_04_000001) that Larastan cannot read.
 *
 * @property string|null $departure_at
 */
class TicketPurchase extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (self $ticket) => app(FlightPackageTicketBilling::class)->forTicket((int) $ticket->id));
        // every edit, from any screen, into the ticket's Trail (2026-10-26) — never throws
        static::updated(fn (self $ticket) => EditLog::record($ticket));
    }

    protected $table = 'ticket_purchases';

    protected $fillable = [
        'passport_holder_id',
        'vendor_id',
        'portal_id',
        'bank_id',
        'ticket_id',
        'order_group',
        'leg_sequence',
        'ticket_type', // 'air', 'bus', 'train', 'other'
        'trip_type', // 'one-way', 'two-way'
        'ticket_class', // 'economy', 'business', 'first' — null on rows recorded before the field existed
        'ticket_no',
        'name_ticket_no', // the passenger's own ticket number under the shared PNR — optional (2026-09-30)
        'source', // 'direct_sale' when created via Ticketing (Direct Sale) form, null otherwise
        // 'from_location',
        // 'to_location',
        // 'travel_date',
        // 'airline_or_operator',
        // 'seat_number',
        // 'description',
        'purchase_date',
        'amount',
        'currency',
        'status', // 'draft', 'pending', 'confirm', 'cancelled'
        'cancel_note', // why a contract flight cancelled it (TicketPurchaseController::cancelFromContractFlight)
        'refund_amount', // what the airline gives back on a cancel; amount − this = penalty (2026-09-28)
        'cancelled_at',  // set when the cancel's reversal was posted — NULL on old, row-deleting cancels
        'attachment',
        'transaction_id',
        'created_by',
        'updated_by',
        'paid_amount',
        'due_amount',
        'payment_status',
        // Standard ticket detail (2026-10-04) — informational; the ledger posts `amount`.
        'base_fare', 'tax_amount', 'tax_breakdown', 'commission_pct', 'commission_amount',
        'validating_carrier_id', 'flight_no', 'departure_at', 'arrival_at', 'rbd', 'baggage',
        'return_flight_no', 'return_at',
        'free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost',
        'extra_baggage_agent_charge', 'extra_baggage_bearer', 'baggage_conditions',
        'company_id',
        // Cancel return (2026-10-14, App\Services\ReturnCancel) — columns guarded by ReturnCancel::ready()
        'family_return', 'return_cancel_by', 'return_cancelled_at', 'return_refund_amount', 'return_penalty', 'return_reminders',
    ];

    protected $casts = [
        'family_return' => 'boolean',
        'return_cancel_by' => 'datetime',
        'return_cancelled_at' => 'datetime',
        'return_reminders' => 'array',
    ];

    /** Fare / flight detail fields an entry row may carry (2026-10-04). */
    public const ENTRY_DETAIL_FIELDS = [
        'base_fare', 'tax_amount', 'tax_breakdown', 'commission_pct', 'commission_amount',
        'validating_carrier_id', 'flight_no', 'departure_at', 'arrival_at', 'rbd', 'baggage',
        'return_flight_no', 'return_at',
        'free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost',
        'extra_baggage_agent_charge', 'extra_baggage_bearer', 'baggage_conditions',
    ];

    /**
     * The fare / flight detail of one entry row, limited to the columns this
     * server's $table actually has — before the 2026-10-04 migration runs it
     * returns [] and the save behaves exactly as it did.
     */
    /**
     * The trip type a row may be stored with. Multi-city exists only once the
     * 2026-10-04 migration widened the enum (it adds return_flight_no in the
     * same run); before that it is stored as one-way rather than failing.
     */
    public static function storableTripType(?string $tripType): string
    {
        $tripType = $tripType ?: 'one-way';
        if ($tripType === 'multi-city' && ! Schema::hasColumn('ticket_purchases', 'return_flight_no')) {
            return 'one-way';
        }

        return $tripType;
    }

    public static function entryDetailAttributes(string $table, array $row): array
    {
        static $have = [];
        $out = [];
        foreach (self::ENTRY_DETAIL_FIELDS as $col) {
            $have[$table][$col] ??= Schema::hasColumn($table, $col);
            if (! $have[$table][$col] || ! array_key_exists($col, $row)) {
                continue;
            }
            $v = is_string($row[$col]) ? trim($row[$col]) : $row[$col];
            $out[$col] = ($v === '' || $v === null) ? null : $v;
        }
        if (isset($out['rbd'])) {
            $out['rbd'] = strtoupper(substr($out['rbd'], 0, 4));
        }
        if (isset($out['flight_no'])) {
            $out['flight_no'] = strtoupper($out['flight_no']);
        }
        if (isset($out['return_flight_no'])) {
            $out['return_flight_no'] = strtoupper($out['return_flight_no']);
        }

        return $out;
    }

    /**
     * The airline commission on its own line (owner, 2026-10-07): the seller
     * is billed the net (amount); a separate entry (source ticket_commission)
     * grosses the cost up and books the commission as income,
     * Dr 5110 Ticket Cost / Cr 4171 IATA Air Ticket Commission.
     *
     * Brings what is posted in line with commission_amount by ADDING the
     * difference — nothing is edited or deleted. First save posts it all; an
     * edit from 3,250 to 3,000 posts a 250 reversal (Dr 4171 / Cr 5110); an
     * old ticket that never had the entry gets one. Missing account: logged,
     * nothing posted, the save goes on (the old netted way).
     */
    public function syncCommissionJournal(): void
    {
        $costId = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
        $incomeId = Account::where('code', config('accounts.ticket_commission_income'))->value('id');
        if (! $costId || ! $incomeId) {
            Log::warning('Ticket commission not posted: account '.config('accounts.ticket_commission_income').' missing', ['ticket_purchase_id' => $this->id]);

            return;
        }
        $journalIds = JournalEntry::where('source', 'ticket_commission')->where('source_id', $this->id)->pluck('id');
        $posted = $journalIds->isEmpty() ? 0.0 : (float) JournalItem::whereIn('journal_entry_id', $journalIds)
            ->where('account_id', $incomeId)->selectRaw('COALESCE(SUM(credit - debit), 0) n')->value('n');
        $diff = round((float) ($this->commission_amount ?? 0) - $posted, 2);
        if (abs($diff) < 0.01) {
            return;
        }
        $journal = JournalEntry::create([
            'company_id' => $this->company_id ?? 2,
            'created_by' => auth()->id(),
            'date' => $journalIds->isEmpty() ? $this->purchase_date : now()->toDateString(),
            'reference' => $this->ticket_no,
            'source' => 'ticket_commission',
            'source_id' => $this->id,
            'description' => ($journalIds->isEmpty() ? 'Airline commission — ' : 'Airline commission adjusted — ').$this->ticket_no,
            'beneficiary' => $this->vendor?->name,
            'beneficiary_party_id' => $this->vendor_id,
        ]);
        $amt = abs($diff);
        $lines = $diff > 0
            ? [[$costId, $amt, 0, 'Full fare — commission grossed up'], [$incomeId, 0, $amt, 'Commission earned on the ticket']]
            : [[$incomeId, $amt, 0, 'Commission reduced'], [$costId, 0, $amt, 'Cost back to the net fare']];
        foreach ($lines as [$acc, $dr, $cr, $note]) {
            JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $acc, 'debit' => $dr, 'credit' => $cr, 'note' => $note]);
        }
    }

    public function passportHolder(): BelongsTo
    {
        return $this->belongsTo(PassportHolder::class, 'passport_holder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /** @return BelongsTo<Portal, $this> */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class, 'portal_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }

    public function portalBalances()
    {
        return $this->hasMany(PortalBalance::class, 'invoice_id');
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id')->withTrashed();
    }

    // public function transaction()
    // {
    //     return $this->belongsTo(Transaction::class, 'transaction_id');
    // }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<TicketLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(TicketLeg::class, 'ticket_purchase_id');
    }

    /**
     * A round trip whose return half was cancelled (2026-10-14) stays live at its
     * full price; what came off it is the airline's refund for that half and, with
     * $withPenalty, what the airline kept — which readers that carry the penalty as
     * its own cost (a contract flight's "airline kept") must not count twice.
     * 0 for every other ticket, and before the migration.
     */
    public function returnTakenOff(bool $withPenalty = true): float
    {
        if (! $this->return_cancelled_at || $this->status === 'cancelled') {
            return 0.0;
        }

        return round((float) ($this->return_refund_amount ?? 0) + ($withPenalty ? (float) ($this->return_penalty ?? 0) : 0.0), 2);
    }

    /** When this ticket flies: the first leg's date, else the entry's departure_at. "Y-m-d H:i:s" or null. */
    public function travelAt(): ?string
    {
        $leg = $this->legs()->orderBy('id')->value('travel_date');
        $at = $leg ?: ($this->departure_at ?? null);

        return $at ? Carbon::parse($at)->format('Y-m-d H:i:s') : null;
    }

    /**
     * A re-issue / reschedule moves the ticket to a new date (2026-10-06).
     * Ticket Orders reads departure_at and the contract flight reads the
     * first leg, so both move together — one would leave the two screens
     * showing different days. $new is "Y-m-d" or "Y-m-d H:i"; a date alone
     * keeps the ticket's time of day. Returns [old, new] ("Y-m-d H:i:s"),
     * or null when the date did not change.
     */
    public function moveTravelDate(string $new): ?array
    {
        $new = trim(str_replace('T', ' ', $new));
        if ($new === '') {
            return null;
        }
        $old = $this->travelAt();
        $hasTime = (bool) preg_match('/\d:\d\d/', $new);
        $time = $old ? substr($old, 11, 8) : '00:00:00';
        $to = Carbon::parse($hasTime ? substr($new, 0, 16) : substr($new, 0, 10).' '.$time)->format('Y-m-d H:i:s');
        if ($old === $to) {
            return null;
        }
        $leg = $this->legs()->orderBy('id')->first();
        if ($leg) {
            $leg->update(['travel_date' => $to]);
        }
        if (Schema::hasColumn($this->getTable(), 'departure_at')) {
            $this->forceFill(['departure_at' => $to])->save();
        }

        return [$old, $to];
    }

    /** @return MorphMany<PaymentSchedule, $this> */
    public function schedules(): MorphMany
    {
        return $this->morphMany(PaymentSchedule::class, 'schedulable');
    }

    /** Lower-case country code of an airport given by its IATA code ("DAC" → "bd"); null when unknown. */
    public static function airportCountry(?string $code): ?string
    {
        static $map = null;
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        try {
            $map ??= DB::table('airports')->leftJoin('countries', 'countries.id', '=', 'airports.country_id')
                ->whereNotNull('airports.code')->select('airports.code as ap', 'countries.code as cc')->pluck('cc', 'ap')->all();
        } catch (\Throwable $e) {
            $map = [];
        }

        return isset($map[$code]) && $map[$code] ? strtolower($map[$code]) : null;
    }

    /** The validating carrier's name, else the route's airline. Never throws. */
    public function validatingCarrierName(): ?string
    {
        try {
            if (! empty($this->validating_carrier_id)) {
                return Airline::whereKey($this->validating_carrier_id)->value('name');
            }

            return $this->ticket?->airline_id ? Airline::whereKey($this->ticket->airline_id)->value('name') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function flightTicketDetails(): array
    {
        $this->loadMissing(['ticket', 'vendor', 'portal', 'bank', 'legs', 'schedules']);

        return [
            'id' => $this->id,
            'passport_holder_id' => $this->passport_holder_id,   // the flight's PDF import matches cards by it (2026-10-08)
            'passenger' => $this->passportHolder?->name,
            'ticket_no' => $this->ticket_no,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'route' => $this->ticket?->title,
            'ticket_id' => $this->ticket_id,
            'leg_sequence' => $this->leg_sequence,
            'trip_type' => $this->trip_type,
            'ticket_class' => $this->ticket_class,
            'purchase_date' => $this->purchase_date,
            'vendor' => $this->vendor?->name,
            'vendor_id' => $this->vendor_id,
            'portal' => $this->portal?->name,
            'bank' => $this->bank?->name,
            'currency' => $this->currency,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'payment_status' => $this->payment_status,
            ...array_intersect_key($this->getAttributes(), array_flip(TicketBaggage::FIELDS)),
            'cost_due_date' => $this->schedules->where('type', 'pay')->sortBy('scheduled_date')->first()?->scheduled_date,
            // Earlier travel dates of this PNR, from re-assign (2026-09-30).
            'was_dates' => ContractFlightPassengerChange::wasDatesLine($this->ticket_no),
            // e-ticket number and the airline that issued it, for the flight card (2026-10-08)
            'name_ticket_no' => $this->name_ticket_no ?? null,
            // the contract flight card lists what a ticket still lacks (2026-10-17)
            'flight_no' => $this->flight_no ?? null,
            'departure_at' => $this->departure_at ? (string) $this->departure_at : null,
            'carrier' => $this->validatingCarrierName(),
            'legs' => $this->legs->map(fn ($leg) => [
                'from_location' => $leg->from_location,
                'to_location' => $leg->to_location,
                // country of each end, for the flags on the itinerary
                'from_cc' => self::airportCountry($leg->from_location),
                'to_cc' => self::airportCountry($leg->to_location),
                'airline' => $leg->airline_id ? $leg->airline?->name : null,   // the sector's own airline, when set
                'travel_date' => $leg->travel_date,
                'cost' => $leg->cost,
            ])->values(),
        ];
    }

    // =========================================================================
    // NAME TICKET NO.  (owner, 2026-09-30)
    // =========================================================================
    // The PNR is the group's; each name on it has its own ticket number. It is
    // optional and informational — nothing is keyed or allocated on it.

    /**
     * ['name_ticket_no' => value] ready to merge into a create/update, or []
     * when the column is not there yet (migration not run on this server) —
     * so a lagging deploy still saves tickets exactly as before.
     * Pass $present = false when the caller never sent the field, so an old
     * form or embedded caller cannot blank a number already on file.
     */
    public static function nameTicketNoAttributes(string $table, $value, bool $present = true): array
    {
        static $hasColumn = [];
        try {
            $hasColumn[$table] ??= Schema::hasColumn($table, 'name_ticket_no');
        } catch (\Throwable $e) {
            $hasColumn[$table] = false;
        }
        if (! $hasColumn[$table] || ! $present) {
            return [];
        }
        $value = trim((string) $value);

        return ['name_ticket_no' => $value === '' ? null : mb_substr($value, 0, 100)];
    }

    // =========================================================================
    // PNR SHARING  (owner, 2026-09-22)
    // =========================================================================
    // A PNR is the AIRLINE's booking reference and is shared by everyone
    // booked together: an agent who hands over five passports gets one PNR,
    // not five. So `ticket_no` repeats by design — the table's unique index
    // was already dropped for this on 2026-05-23 — and rows are told apart by
    // a bookkeeping suffix appended as they save:
    //
    //     JK2564VB        JK2564VB (a)        JK2564VB (b)
    //
    // The space is deliberate: it keeps the airline's own reference one whole
    // token, so `like '%JK2564VB%'` still finds the entire group and anyone
    // reading the row sees the real PNR before our letter.
    //
    // The letter identifies the PERSON on the booking, not the row. A via
    // route (DAC-IST-ECN) is two ticket_purchases rows for one passenger and
    // they carry the SAME letter — hence the passport-holder argument.
    //
    // There is deliberately no guard at entry: type the same PNR on every
    // card. Allocation happens here, on save.

    /** Matches our own " (a)" / " (ab)" suffix, never a PNR's own characters. */
    public const PNR_SUFFIX_PATTERN = '/\s*\(([a-z]+)\)\s*$/i';

    /** The airline's reference with our suffix stripped: "JK2564VB (a)" → "JK2564VB". */
    public static function basePnr(?string $pnr): ?string
    {
        $pnr = trim((string) $pnr);
        if ($pnr === '') {
            return null;
        }

        return trim((string) preg_replace(self::PNR_SUFFIX_PATTERN, '', $pnr)) ?: null;
    }

    /** 1 → "a", 26 → "z", 27 → "aa". A PNR holds at most 9 people, so this never runs far. */
    public static function pnrSuffixLabel(int $n): string
    {
        $label = '';
        while ($n > 0) {
            $n--;
            $label = chr(97 + ($n % 26)).$label;
            $n = intdiv($n, 26);
        }

        return $label !== '' ? $label : 'a';
    }

    /**
     * The value to store for a PNR someone just typed.
     *
     * Keeps what was typed when nothing else holds it, so a PNR entered once
     * stays bare and a re-save never renames itself. Otherwise it returns the
     * letter already belonging to this passenger on this booking, or the next
     * free one.
     *
     * $opts: ignore_purchase_id / ignore_booking_id — the row being edited or
     * the hold being converted, which must not count as competition with
     * itself.
     */
    public static function allocateTicketNo(?string $pnr, $passportHolderId = null, array $opts = []): ?string
    {
        $requested = trim((string) $pnr);
        if ($requested === '') {
            return null;
        }

        $base = self::basePnr($requested);
        if ($base === null) {
            return null;
        }

        $taken = self::pnrGroupRows($base, $opts);

        // Free exactly as typed — including a suffix carried over from a hold.
        if (! $taken->has(mb_strtolower($requested))) {
            return $requested;
        }

        // This passenger is already on this booking (the other leg of a via
        // route): reuse their letter rather than inventing a second one.
        if ($passportHolderId) {
            $mine = $taken->first(fn ($row) => (int) $row->passport_holder_id === (int) $passportHolderId);
            if ($mine) {
                return $mine->ticket_no;
            }
        }

        if (! $taken->has(mb_strtolower($base))) {
            return $base;
        }

        for ($n = 1; $n <= 999; $n++) {
            $candidate = $base.' ('.self::pnrSuffixLabel($n).')';
            if (! $taken->has(mb_strtolower($candidate))) {
                return $candidate;
            }
        }

        return $base.' ('.uniqid().')';
    }

    /**
     * Every row already holding this PNR, keyed by its lower-cased value.
     *
     * Reads holds as well as purchases: Ticket Orders lists both, so a letter
     * handed to a booking must not be handed to a purchase too. A confirmed
     * hold is skipped because it and its purchase are the same ticket.
     */
    private static function pnrGroupRows(string $base, array $opts = []): Collection
    {
        $like = addcslashes($base, '%_\\').' (%';

        $rows = static::query()
            ->where(fn ($q) => $q->where('ticket_no', $base)->orWhere('ticket_no', 'like', $like))
            ->when($opts['ignore_purchase_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            ->lockForUpdate()
            ->get(['id', 'ticket_no', 'passport_holder_id']);

        // Holds may be missing on a server whose migrations lag; a PNR must
        // still be allocatable when they are.
        try {
            $bookings = TicketBooking::query()
                ->where(fn ($q) => $q->where('pnr', $base)->orWhere('pnr', 'like', $like))
                ->whereNull('confirmed_purchase_id')
                ->where('status', '!=', 'confirmed')
                ->when($opts['ignore_booking_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
                ->get(['id', 'pnr as ticket_no', 'passport_holder_id']);
            $rows = $rows->concat($bookings);
        } catch (\Throwable) {
            // purchases alone still give a usable answer
        }

        return $rows->keyBy(fn ($row) => mb_strtolower(trim((string) $row->ticket_no)));
    }
}
