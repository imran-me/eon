<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaRequiredDocument extends Model
{
    protected $table = 'visa_required_documents';

    protected $fillable = [
        'visa_type',
        'document_name',
        'is_mandatory',
        'sort_order',
    ];

    public static function getForType(string $visaType): \Illuminate\Support\Collection
    {
        return static::where('visa_type', $visaType)
            ->orderBy('sort_order')
            ->orderBy('document_name')
            ->get();
    }
}
