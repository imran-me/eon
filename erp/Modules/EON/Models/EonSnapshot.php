<?php

namespace Modules\EON\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The engine's reading of one person, frozen at the moment a review was
 * written. See the migration for why this is stored whole rather than
 * column-per-metric.
 *
 * Guarded by installed() for the same reason EonReview is: the table arrives by
 * migration, and EON has to work on a server where that has not happened yet.
 */
class EonSnapshot extends Model
{
    protected $table = 'eon_snapshots';

    protected $fillable = [
        'user_id',
        'period',
        'score',
        'band',
        'confidence',
        'payload',
        'taken_by',
        'taken_at',
    ];

    protected $casts = [
        'payload'  => 'array',
        'score'    => 'float',
        'taken_at' => 'datetime',
    ];

    private static ?bool $installed = null;

    public static function installed(): bool
    {
        if (self::$installed === null) {
            try {
                self::$installed = Schema::hasTable('eon_snapshots');
            } catch (Throwable $e) {
                self::$installed = false;
            }
        }

        return self::$installed;
    }

    /**
     * Read a value out of the frozen document without assuming its shape.
     * Old snapshots were written by older analyzers and will be missing keys
     * that exist today; that is expected, not a fault.
     */
    public function value(string $key, $default = null)
    {
        return data_get($this->payload, $key, $default);
    }
}
