<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeTodoChecklist extends Model
{
    protected $fillable = [
        'office_todo_id',
        'parent_id',
        'title',
        'priority',
        'status',
        'start_date',
        'end_date',
        'sort_order',
        'is_checked',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
        // Pinned to Y-m-d: a bare 'date' cast serialises to UTC, and on
        // Asia/Dhaka (UTC+6) that pushes the JSON back to the previous day —
        // clients slicing the first 10 characters then show the wrong date.
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    /**
     * `is_checked` is the canonical "this is done" flag — the mobile API still
     * writes it on its own without knowing about `status` — so a ticked row
     * always reads back as completed no matter what the column happens to hold.
     */
    public function getStatusAttribute($value): string
    {
        if (!empty($this->attributes['is_checked'])) {
            return 'completed';
        }

        return $value ?: 'pending';
    }

    public function todo()
    {
        return $this->belongsTo(OfficeTodo::class, 'office_todo_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Only rows that have no children of their own. Progress is measured on
     * these alone, so a parent doesn't get counted twice — once for itself and
     * again through the sub-items that make it up.
     */
    public function scopeLeafOnly($query)
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw(1)
                ->from('office_todo_checklists as child')
                ->whereColumn('child.parent_id', 'office_todo_checklists.id');
        });
    }
}
