<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = "attendances";

    protected $fillable = [
        "user_id",
        "company_id",
        "shift_id",
        "employee_id",
        "attendence_setting_id",
        "date",
        "check_in",
        "check_out",
        "note",
        "status",
        "source",
        "selfie",
        "submitted_at",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    public function attendence_setting()
    {
        return $this->belongsTo(AttendenceSetting::class);
    }
}
