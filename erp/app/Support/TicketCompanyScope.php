<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class TicketCompanyScope
{
    /** Ticketing is Travels-owned; the operator's home company is audit data only. */
    public static function resolve(Request $request, ?User $user): int
    {
        return TravelCompany::ID;
    }
}
