<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSaleItem extends Model
{

    protected $table = 'ticket_sale_items';

    protected $fillable = [
        'ticket_sale_id','ticket_purchase_id','price',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(TicketSale::class, 'ticket_sale_id');
    }

    public function ticketPurchase(): BelongsTo
    {
        return $this->belongsTo(TicketPurchase::class, 'ticket_purchase_id');
    }
}
