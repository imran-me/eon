<?php

namespace Modules\WoodArt\Modules\Estimates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Estimate — one quotation and the bill of quantities behind it.
 *
 * ─── OWNERSHIP: THIS MODEL SITS OVER A TABLE IT DOES NOT OWN ─────────────
 * `wa_estimates` is CREATED BY THE PROJECTS MODULE — its migration lives in
 * Modules/Projects/Database/Migrations, alongside wa_projects, because the
 * estimates are the spine the rest of Wood Art points at and existed before
 * this module did. Estimates owns the SCREENS and the WRITE PATH; Projects
 * keeps the table. That split is the reference's own (module.json
 * "ownership"), and it is deliberate — a local model over a table owned
 * elsewhere is not an oversight to be tidied away. **Do not add a second
 * migration for this table here.**
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one `wa_`-prefixed table. A global scope pins every
 * query and insert to `company_id = 'woodart'`.
 *
 * ─── THE LINES COLUMN ────────────────────────────────────────────────────
 * `lines` is a JSON array, one entry per BOQ line. The live data carries MORE
 * than the reference seam documents:
 *
 *   { code:'Cement', item:'Cement — 50 kg bag', kind:'material',
 *     unit:'bag', qty:550, unitCost:545, unitSale:626 }
 *
 * `code` groups lines into trades, `kind` is work|material, `unit` is the
 * quantity's unit. Every write path MUST round-trip all seven keys — dropping
 * `code`, `kind` or `unit` because the reference's own api.js only normalises
 * four would silently destroy a real 24-line bill of quantities.
 *
 * ─── MONEY ───────────────────────────────────────────────────────────────
 * Nothing here posts to any ledger. An estimate is a quotation: it states what
 * a job would cost and what it would be sold for. Turning one into money is
 * Projects' billing, and this module must never grow a second path to it.
 *
 * @property int         $id
 * @property string      $ext_id
 * @property string      $title
 * @property string|null $client       a client NAME, not an FK
 * @property string|null $project_ext  a project EXT id ('WAP-101'), not an FK
 * @property string      $status
 * @property array       $lines
 * @property \Illuminate\Support\Carbon|null $valid_till
 * @property-read int    $cost
 * @property-read int    $sale
 * @property-read int    $margin
 * @property-read int    $margin_pct
 * @property-read int    $line_count
 * @property-read bool   $is_expired
 */
class Estimate extends Model
{
    use SoftDeletes;

    protected $table = 'wa_estimates';

    public const COMPANY = 'woodart';

    /** Verbatim from the reference seam (api.js STATUSES). */
    public const STATUSES = ['Draft', 'Sent', 'Approved', 'Rejected'];

    /** What a BOQ line is: labour, or something bought. From the live data. */
    public const KINDS = ['material', 'work'];

    /** Units seen in the live bill of quantities, plus the register's own. */
    public const UNITS = [
        'lot', 'pcs', 'set', 'point', 'bag', 'kg', 'litre',
        'sheet', 'sft', 'rft', 'cft',
    ];

    /** Every key a BOQ line carries. The write path round-trips all of them. */
    public const LINE_KEYS = ['code', 'item', 'kind', 'unit', 'qty', 'unitCost', 'unitSale'];

    protected $fillable = [
        'ext_id', 'company_id', 'title', 'client', 'project_ext',
        'status', 'lines', 'valid_till', 'created_on',
    ];

    protected $casts = [
        'lines'      => 'array',
        'valid_till' => 'date',
        'created_on' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $e) {
            $e->company_id = $e->company_id ?: self::COMPANY;
        });
    }

    /** The one date "expired" is measured against. Real, not the demo clock. */
    public static function today(): string
    {
        return now()->toDateString();
    }

    /** Normalise one submitted line into the stored shape, keeping every key. */
    public static function normaliseLine(array $l): array
    {
        return [
            'code'     => trim((string) ($l['code'] ?? '')),
            'item'     => trim((string) ($l['item'] ?? '')),
            'kind'     => \in_array($l['kind'] ?? '', self::KINDS, true) ? $l['kind'] : 'material',
            'unit'     => trim((string) ($l['unit'] ?? '')) ?: 'pcs',
            'qty'      => (float) ($l['qty'] ?? 0),
            'unitCost' => (int) round((float) ($l['unitCost'] ?? 0)),
            'unitSale' => (int) round((float) ($l['unitSale'] ?? 0)),
        ];
    }

    /* ── derived, never stored ────────────────────────────────────────────── */

    /** The lines, always an array even when the column is null or malformed. */
    public function getLineRowsAttribute(): array
    {
        return \is_array($this->lines) ? $this->lines : [];
    }

    public function getCostAttribute(): int
    {
        return (int) array_sum(array_map(
            fn (array $l) => (float) ($l['qty'] ?? 0) * (float) ($l['unitCost'] ?? 0),
            $this->line_rows
        ));
    }

    public function getSaleAttribute(): int
    {
        return (int) array_sum(array_map(
            fn (array $l) => (float) ($l['qty'] ?? 0) * (float) ($l['unitSale'] ?? 0),
            $this->line_rows
        ));
    }

    public function getMarginAttribute(): int
    {
        return $this->sale - $this->cost;
    }

    /** Margin as a share of the SALE price, which is how a quote is judged. */
    public function getMarginPctAttribute(): int
    {
        return $this->sale > 0 ? (int) round($this->margin / $this->sale * 100) : 0;
    }

    public function getLineCountAttribute(): int
    {
        return \count($this->line_rows);
    }

    /**
     * Past its validity and STILL AWAITING AN ANSWER.
     *
     * An approved or rejected estimate is never "expired" — it has already been
     * answered, and flagging it red because a date passed would be telling the
     * user off for winning the job.
     */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->valid_till === null) {
            return false;
        }

        if (\in_array($this->status, ['Approved', 'Rejected'], true)) {
            return false;
        }

        return $this->valid_till->toDateString() < self::today();
    }

    /* ── scopes ───────────────────────────────────────────────────────────── */

    public function scopeAnswered(Builder $q): Builder
    {
        return $q->whereIn('status', ['Approved', 'Rejected']);
    }
}
