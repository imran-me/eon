<?php

namespace App\Traits;

use App\Models\User;

/**
 * Resolves the PaymentSchedule/JournalItem `party_type` string ('agent' |
 * 'vendor' | 'customer') from a party's actual Spatie role, instead of
 * hardcoding one — a sale's `client_id` can point at an agent, a vendor, or
 * (rarely, in this business) a plain customer, and the label should match
 * whichever the party really is. Shared so ticket/visa/flight/file modules
 * resolve it the same way instead of each guessing.
 */
trait ResolvesPartyType
{
    protected function resolvePartyType(?User $party): string
    {
        if (!$party) {
            return 'customer';
        }

        if ($party->hasRole('agent')) {
            return 'agent';
        }

        if ($party->hasRole('vendor')) {
            return 'vendor';
        }

        return 'customer';
    }
}
