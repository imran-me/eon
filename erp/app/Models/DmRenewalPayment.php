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
        'due_date',
        'title',
        'amount',
        'currency',
        'expense_id',
        'recorded_by',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at'  => 'datetime',
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
