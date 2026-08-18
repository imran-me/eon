<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = [
        'company_id',
        'department_id',
        'created_by',
        'title',
        'description',
        'publish_date',
        'expiry_date',
        'status',
        'icon',
        'card_color',
        'text_color',
        'badge_icon',
        'badge_label',
        'slide_image',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
