<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContractFileCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'country_id',
        'visa_rate',
        'required_documents',
        'status',
        'created_by',
    ];

    protected $casts = [
        'visa_rate' => 'float',
    ];

    protected $appends = [
        'documents_count',
        'documents_list',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDocumentsListAttribute(): array
    {
        if (!$this->required_documents) {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/', $this->required_documents))
            ->map(fn ($doc) => trim($doc))
            ->filter()
            ->values()
            ->all();
    }

    public function getDocumentsCountAttribute(): int
    {
        return count($this->documents_list);
    }

    public static function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = 'FC-' . strtoupper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        $base = $base === 'FC-' ? 'FC-CAT' : $base;
        $code = $base;
        $counter = 1;

        while (static::withTrashed()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('code', $code)
            ->exists()) {
            $code = $base . '-' . $counter++;
        }

        return $code;
    }
}
