<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'paper_size',
        'orientation',
        'is_default',
        'layout_config',
        'branch_id'
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_default' => 'boolean',
    ];

    public function fields()
    {
        return $this->hasMany(InvoiceTemplateField::class)->orderBy('sort_order');
    }

    public function style()
    {
        return $this->hasOne(InvoiceTemplateStyle::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
