<?php

namespace Modules\WoodArt\Modules\Clients\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wood Art · Client — a homeowner, developer, corporate or retail buyer.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Reads and writes exactly one table, `wa_clients`, which no other company
 * touches. A global scope pins every query AND every insert to
 * `company_id = 'woodart'`, so a bare Client::all() cannot surface another
 * concern's rows and a new client cannot be written under another key by
 * accident. That string is the reference build's own company key, not the
 * ERP's numeric companies.id of 6 — see Projects\Models\Project for why it
 * must not be "corrected".
 *
 * @property int                             $id
 * @property string                          $ext_id
 * @property string                          $company_id
 * @property string                          $name
 * @property string                          $type
 * @property string|null                     $contact
 * @property string|null                     $phone
 * @property string|null                     $email
 * @property string|null                     $area
 * @property \Illuminate\Support\Carbon|null $since
 * @property \Illuminate\Support\Carbon|null $created_on
 */
class Client extends Model
{
    use SoftDeletes;

    protected $table = 'wa_clients';

    /** The tenant key every row carries. */
    public const COMPANY = 'woodart';

    /** Segments — verbatim from the reference (clients/view.js TYPES). */
    public const TYPES = ['Homeowner', 'Developer', 'Corporate', 'Retail'];

    protected $fillable = [
        'ext_id', 'company_id', 'name', 'type', 'contact', 'phone', 'email',
        'area', 'since', 'created_on',
    ];

    protected $casts = [
        'since'      => 'date',
        'created_on' => 'date',
    ];

    /**
     * Bind on `ext_id` ("WAC-101"), never the numeric id — the same rule as
     * Projects, and what keeps a client URL readable and shareable.
     */
    public function getRouteKeyName(): string
    {
        return 'ext_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('woodart', fn (Builder $q) => $q->where('company_id', self::COMPANY));
        static::creating(function (self $c) {
            $c->company_id = $c->company_id ?: self::COMPANY;
        });
    }
}
