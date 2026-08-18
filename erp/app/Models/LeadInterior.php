<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadInterior extends Model
{
    protected $table = 'lead_interiors';

    protected $fillable = [
        'lead_id',
        'property_type',
        'area_sqft',
        'style_preference',
        'room_details',
        'budget_min',
        'budget_max',
        'site_visit_date',
        'site_visit_duration',
        'site_visit_done',
        'measurement_file',
        'design_notes',
    ];

    protected $casts = [
        'room_details'     => 'array',
        'site_visit_done'  => 'boolean',
        'site_visit_date'  => 'date',
        'budget_min'       => 'decimal:2',
        'budget_max'       => 'decimal:2',
        'area_sqft'        => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function getPropertyTypeLabelAttribute(): string
    {
        return match($this->property_type) {
            'flat'       => 'Flat / Apartment',
            'house'      => 'House / Villa',
            'office'     => 'Office Space',
            'shop'       => 'Shop / Showroom',
            'restaurant' => 'Restaurant / Cafe',
            default      => 'Other',
        };
    }

    public function getStyleLabelAttribute(): string
    {
        return match($this->style_preference) {
            'modern'       => 'Modern',
            'classic'      => 'Classic',
            'minimalist'   => 'Minimalist',
            'contemporary' => 'Contemporary',
            'traditional'  => 'Traditional',
            default        => ucfirst($this->style_preference ?? 'Not Set'),
        };
    }

    public function getBudgetRangeAttribute(): string
    {
        if ($this->budget_min && $this->budget_max) {
            return number_format($this->budget_min) . ' – ' . number_format($this->budget_max) . ' BDT';
        }
        if ($this->budget_max) {
            return 'Up to ' . number_format($this->budget_max) . ' BDT';
        }
        return 'Not Set';
    }
}
