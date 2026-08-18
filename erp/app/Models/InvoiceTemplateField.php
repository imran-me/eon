<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplateField extends Model
{
    protected $fillable = [
        'invoice_template_id',
        'label',
        'key',
        'type',
        'section',
        'sort_order',
        'is_required',
        'is_visible'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(InvoiceTemplate::class);
    }
}
