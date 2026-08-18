<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplateStyle extends Model
{
    protected $fillable = [
        'invoice_template_id',
        'show_border',
        'striped_table',
        // 'font_family',
        // 'primary_color',
        // 'secondary_color',
        'title_color',
        'title_bg',
        'tabler_header_bg',
        'text_color',
        'title_font',
        'text_font',
        'number_font'
    ];

    protected $casts = [
        'show_border' => 'boolean',
        'striped_table' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(InvoiceTemplate::class);
    }
}
