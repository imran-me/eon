<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractFileServiceCost extends Model
{
    public const SOURCE = 'contract_file_service_cost';
    protected $guarded = ['id'];
    protected $casts = ['amount'=>'float','paid_amount'=>'float','incurred_on'=>'date','payment_date'=>'date','due_date'=>'date','voided_at'=>'datetime'];
    public function file() { return $this->belongsTo(ContractFile::class, 'contract_file_id'); }
    public function vendor() { return $this->belongsTo(User::class, 'vendor_id'); }
    public function bank() { return $this->belongsTo(Bank::class); }
    public function refundChanges() { return $this->hasMany(ContractFileChange::class, 'service_cost_id')->where('type','refund'); }
    public function getNetAmountAttribute(): float
    {
        $refund = \Illuminate\Support\Facades\Schema::hasTable('contract_file_changes') ? $this->refundChanges->sum('vendor_refund') : 0;
        return max(0, $this->amount - $refund);
    }
    public function getDueAmountAttribute(): float { return $this->voided_at ? 0 : max(0,$this->net_amount-$this->paid_amount); }
    public function getRefundCreditAttribute(): float { return max(0,$this->paid_amount-$this->net_amount); }
}
