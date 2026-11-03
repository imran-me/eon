<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contract File → Setup → Requirements: a document list for a scope — a country, a visa type, a job
 * category, or any mix (an empty part means "any"). Every rule that matches a file adds its documents
 * to the file's checklist (App\Services\ContractFileSetup::resolve()).
 *
 * @property string|null $documents
 */
class ContractFileRequirement extends Model
{
    use SoftDeletes;

    protected $fillable = ['country_id', 'contract_file_visa_type_id', 'contract_file_category_id', 'documents', 'notes', 'status', 'created_by'];

    protected $appends = ['documents_list'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(ContractFileVisaType::class, 'contract_file_visa_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractFileCategory::class, 'contract_file_category_id');
    }

    /** One document per line (or comma), blanks and repeats dropped — the same reading as a category's list. */
    public function getDocumentsListAttribute(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $this->documents))
            ->map(fn ($doc) => trim($doc))
            ->filter()
            ->unique(fn ($doc) => mb_strtolower($doc))
            ->values()
            ->all();
    }
}
