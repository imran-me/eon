<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contract File → Setup → Price Presets: processing cost, training fee and expected days for a scope
 * (country / visa type / job category, any mix). For each figure the most specific matching preset that
 * HAS that figure wins (App\Services\ContractFileSetup::resolve()); the client price is always typed.
 */
class ContractFilePricePreset extends Model
{
    use SoftDeletes;

    protected $fillable = ['country_id', 'contract_file_visa_type_id', 'contract_file_category_id', 'processing_cost', 'training_fee', 'expected_days', 'status', 'created_by'];

    protected $casts = ['processing_cost' => 'float', 'training_fee' => 'float', 'expected_days' => 'integer'];

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
}
