<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Log\CustomerLog;
use App\Models\Log\VendorLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use SoftDeletes;

    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        "name",
        "device_user_id",
        "employee_id_no",
        "company_id",
        "salary_template_id",
        "shift_id",
        "device_id",
        "email",
        "password",
        "username",
        "image",
        "is_super_admin",
        "email_verified_at",
        "remember_token",
        "phone",
        "address",
        "contact_person",
        "status", // Added status field
        "deleted_at",
        "created_at",
        "updated_at",
        "last_seen_at",
        "two_factor_secret",
        "two_factor_confirmed_at",
        "allow_manual_attendance",
        "overtime_eligible",
        "auto_payslip_enabled",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'last_seen_at'             => 'datetime',
            'two_factor_confirmed_at'  => 'datetime',
            'password'                 => 'hashed',
            'device_user_id'           => 'array',
            'device_ids'               => 'array',
        ];
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->username) && !empty($user->name)) {
                $user->username = static::generateUniqueUsername($user->name);
            }
        });
    }

    protected static function generateUniqueUsername($name)
    {
        $base = Str::slug($name);
        $username = $base;
        $counter = 1;

        while (static::where('username', $username)->exists()) {
            $username = $base . '-' . $counter++;
        }

        return $username;
    }

    public function logs()
    {
        return $this->hasMany(VendorLog::class, 'vendor_id');
    }

    public function customerLogs()
    {
        return $this->hasMany(CustomerLog::class, 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function profile()
    {
        return $this->hasOne(EmployeeProfile::class, 'user_id');
    }
    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    public function salary_template()
    {
        return $this->belongsTo(SalaryTemplate::class, 'salary_template_id', 'id');
    }
    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    public function employeeDocument()
    {
        return $this->hasOne(EmployeeDocument::class, 'user_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function employeeSalaries()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function advanceSalaries()
    {
        return $this->hasMany(AdvanceSalary::class);
    }

    /**
     * Ordered by id (true insertion/calculation order), not entry_date —
     * entry_date is a free-typed field on every ledger-posting form (Bonus,
     * Opening Balance, Pay Due Amount, manual salary entry, ...) and can be
     * backdated to anything, so sorting by it can display rows in a
     * different sequence than the one the running balance was actually
     * computed in. Sorting by id keeps the displayed list always matching
     * how each row's old_balance/balance was calculated.
     */
    public function ledgerEntries()
    {
        return $this->hasMany(EmployeeLedger::class)->orderBy('id');
    }
}
