<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\TicketCompanyScope;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TicketCompanyScopeTest extends TestCase
{
    public function test_ticketing_always_uses_the_travel_company(): void
    {
        $user = new TicketScopeUser(['company_id' => 1]);
        $request = Request::create('/tickets', 'GET', ['company' => 4]);

        $this->assertSame(2, TicketCompanyScope::resolve($request, $user));
    }

    public function test_view_all_does_not_remove_the_travel_company_scope(): void
    {
        $user = new TicketScopeUser(['company_id' => 1]);
        $user->allowed = ['view all ticket purchase'];

        $this->assertSame(2, TicketCompanyScope::resolve(Request::create('/tickets'), $user));
    }

    public function test_an_operator_from_another_company_cannot_override_ticket_ownership(): void
    {
        $user = new TicketScopeUser(['company_id' => 1]);
        $user->allowed = ['view all ticket direct sale'];
        $request = Request::create('/tickets', 'GET', ['company' => 1]);

        $this->assertSame(2, TicketCompanyScope::resolve($request, $user));
    }
}

class TicketScopeUser extends User
{
    /** @var array<int, string> */
    public array $allowed = [];

    public function can($abilities, $arguments = []): bool
    {
        return in_array($abilities, $this->allowed, true);
    }
}
