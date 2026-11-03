<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Contract File → Setup → Visa Types: the file module's own list (owner, 2026-10-25 — not Visa
 * Processing's visa categories). A file carries one; requirements and price presets may be scoped to one.
 */
class ContractFileVisaType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'status', 'created_by'];

    public function files(): HasMany
    {
        return $this->hasMany(ContractFile::class, 'contract_file_visa_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = 'VT-'.Str::upper(Str::limit(Str::slug($name, ''), 6, ''));
        $code = $base;
        $n = 1;
        while (static::withTrashed()->where('code', $code)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $code = $base.'-'.(++$n);
        }

        return $code;
    }
}
