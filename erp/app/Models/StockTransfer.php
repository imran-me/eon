<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $table = 'stock_transfers';

    protected $fillable = [
        'transfer_no',    
        'product_id',    
        'from_branch_id',    
        'to_branch_id',    
        'quantity',    
        'note',
        'created_by'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function from_branch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id', 'id');
    }
    public function to_branch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id', 'id');
    }
}
