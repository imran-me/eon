<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One settled billing period of a DM subscription or document renewal.
 *
 * The commitment itself is NOT here — it lives in DM and is fetched live by
 * App\Services\DmApiService. This model records only that a given DM row, for a
 * given due date, was paid by a given expense. See the migration
 * (2026_08_17_000003_create_dm_renewal_payments_table) for why it is split that
 * way and why the unique index matters.
 */
class DmRenewalPayment extends Model
{
    /** DM's company-access endpoint — a service billed on a cycle. */
    public const SUBSCRIPTION = 'subscription';

    /** DM's expired-documents endpoint — a licence, permit or certificate. */
    public const DOCUMENT = 'document';

    protected $fillable = [
        'source_type',
        'dm_id',
        'dm_group_id',
        'dm_type_id',
        'dm_category_id',
        'due_date',
        'next_expiry',
        'title',
        'amount',
        'currency',
        'cycle',
        'expense_id',
        'recorded_by',
        'paid_at',
        'dm_synced_at',
        'dm_sync_error',
    ];

    protected $casts = [
        'due_date' => 'date',
        'next_expiry' => 'date',
        'paid_at'  => 'datetime',
        'dm_synced_at' => 'datetime',
        'amount'   => 'decimal:2',
    ];

    /**
     * The one key that identifies a billing period, used to join DM rows to
     * payments in memory.
     *
     * Built in one place so the lookup and the unique index can never disagree
     * about what "the same period" means — the moment they do, either a paid row
     * shows as unpaid or a second payment slips past the constraint.
     */
    public static function periodKey(string $sourceType, int|string $dmId, string $dueDate): string
    {
        return $sourceType . ':' . $dmId . ':' . $dueDate;
    }

    /**
     * The key that follows ONE commitment across its renewals.
     *
     * For a subscription that is just the DM row: one row, reused every cycle.
     *
     * For a document it is the folder PLUS the type and category, because
     * document_id on its own is only a folder — 388 rows sit under 29 of them,
     * and one folder holds a tax token, a licence and a certificate together.
     * Grouping on the folder alone carried the wrong paper's amount forward.
     */
    public function paperKey(): string
    {
        return $this->source_type . ':' . $this->dm_group_id
            . ':' . ($this->dm_type_id ?? '-') . ':' . ($this->dm_category_id ?? '-');
    }

    /** The same key built from a row on screen, before any payment exists. */
    public static function paperKeyFor(string $sourceType, $groupId, $typeId = null, $categoryId = null): string
    {
        return $sourceType . ':' . $groupId . ':' . ($typeId ?? '-') . ':' . ($categoryId ?? '-');
    }

    /**
     * The due date a DM row means, as Y-m-d.
     *
     * ONE definition, used by every screen that joins DM to this table. The
     * field precedence differs between the two endpoints and getting it wrong on
     * one screen would mean a renewal shown as paid on the Subscriptions desk and
     * still overdue on the dashboard — the exact disagreement this join exists to
     * remove. Returns null rather than throwing on a value DM cannot parse.
     */
    public static function dueDateFor(array $row, string $sourceType): ?string
    {
        $raw = $sourceType === self::SUBSCRIPTION
            // The current term runs out on expired_date; renewal_date is when it
            // began, and is only a fallback so a half-filled record still lists.
            ? (data_get($row, 'expired_date') ?: data_get($row, 'renewal_date'))
            : (data_get($row, 'renewal_date')
                ?: data_get($row, 'expired_date')
                ?: data_get($row, 'documents.renewal_date'));

        if (empty($raw)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->startOfDay()->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Drop the DM rows whose current period already has an expense against it.
     *
     * For the dashboard Renewal Center and the headline ticker, both of which
     * are "what still needs attention" surfaces — a renewal somebody has already
     * paid is not attention-worthy, and leaving it there is what made the
     * dashboard keep chasing something the Subscriptions desk had marked settled.
     *
     * NEVER THROWS. It runs inside the shared dashboard, which every company
     * loads. If dm_renewal_payments does not exist yet — a server whose migration
     * has not been run — this returns the rows untouched, so the Renewal Center
     * behaves exactly as it did before this method existed rather than taking
     * twelve companies' dashboards down.
     *
     * @param  array  $rows  raw DM records
     * @return array  the same records, minus the settled ones
     */
    public static function withoutSettled(array $rows, string $sourceType): array
    {
        if (! $rows) {
            return $rows;
        }

        try {
            $ids = [];

            foreach ($rows as $row) {
                if (! is_array($row) || ! ($id = data_get($row, 'id'))) {
                    continue;
                }

                if (self::dueDateFor($row, $sourceType)) {
                    $ids[] = (int) $id;
                }
            }

            if (! $ids) {
                return $rows;
            }

            $settled = [];

            foreach (self::where('source_type', $sourceType)->whereIn('dm_id', array_unique($ids))->get() as $payment) {
                $settled[self::periodKey($sourceType, $payment->dm_id, $payment->due_date->toDateString())] = true;
            }

            if (! $settled) {
                return $rows;
            }

            return array_values(array_filter($rows, function ($row) use ($sourceType, $settled) {
                if (! is_array($row) || ! ($id = data_get($row, 'id'))) {
                    return true;
                }

                $due = self::dueDateFor($row, $sourceType);

                return ! ($due && isset($settled[self::periodKey($sourceType, $id, $due)]));
            }));
        } catch (\Throwable $e) {
            logger()->warning('Could not check settled DM renewals: ' . $e->getMessage());

            return $rows;
        }
    }

    /**
     * Where the term runs to after this one is paid.
     *
     * The single definition of "one cycle later", used both to push the date into
     * DM and to show what the next period will be. If these ever disagreed, the
     * desk would promise one date and DM would hold another.
     *
     * addMonthsNoOverflow() matters more than it looks: a monthly subscription
     * expiring 31 January must land on 28 February, not spill into 3 March. Plain
     * addMonth() drifts the anniversary forward a few days every short month, and
     * a year of that moves a renewal into the wrong month entirely.
     *
     * An unrecognised cycle returns NULL rather than a guess. DM's register has
     * blank and mistyped cycles in it, and a wrong date written back here does not
     * raise an error anywhere — it silently stops a reminder arriving. Better to
     * leave that one to a person.
     */
    public static function nextExpiryFor(string $dueDate, ?string $cycle): ?string
    {
        try {
            $from = \Carbon\Carbon::parse($dueDate)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        return match ($cycle) {
            'Weekly'      => $from->addWeek()->toDateString(),
            'Monthly'     => $from->addMonthsNoOverflow(1)->toDateString(),
            'Quarterly'   => $from->addMonthsNoOverflow(3)->toDateString(),
            'Half-Yearly' => $from->addMonthsNoOverflow(6)->toDateString(),
            'Yearly'      => $from->addYearNoOverflow()->toDateString(),
            default       => null,
        };
    }

    /** True once DM has been told about this renewal. */
    public function getIsDmSyncedAttribute(): bool
    {
        return $this->dm_synced_at !== null;
    }

    /**
     * Needs a push to DM: a paid subscription period that DM has not confirmed.
     *
     * Documents are excluded — DM records those as a NEW row per renewal rather
     * than by moving a date, so they need a different call that does not exist
     * yet. Reporting them as "sync pending" would be an alert nobody can action.
     */
    public function scopeAwaitingDmSync($query)
    {
        return $query->where('source_type', self::SUBSCRIPTION)->whereNull('dm_synced_at');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    /** Who filed the expense against this renewal. */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
