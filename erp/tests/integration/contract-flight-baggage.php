<?php

use App\Http\Controllers\Dashboard\ContractFlightPassengerController;
use App\Http\Controllers\Dashboard\ManageFlightController;
use App\Models\ContractFlight;
use App\Models\ContractFlightPassenger;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyFunds;
use App\Services\CompanyLedger;
use App\Services\ContractFlightAgentReceipts;
use App\Services\TicketBaggage;
use App\Services\TicketingProfit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Queue::fake();
config(['services.party_ledger.notify' => false]);
$actor = User::where('is_super_admin', 1)->firstOrFail();
auth()->setUser($actor);
Gate::before(fn () => true);
$checks = 0;
$check = function ($condition, string $message) use (&$checks) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$near = fn ($actual, $expected) => abs((float) $actual - (float) $expected) < 0.005;
$agents = User::role('agent')->limit(2)->get();
$vendor = User::role('vendor')->firstOrFail();
$template = TicketPurchase::where('company_id', 2)->where('status', 'confirm')->firstOrFail();
$beforeFunds = CompanyFunds::summary();
$beforeDept = TicketingProfit::compute()['totals']['profit'];
$beforeLedger = CompanyLedger::entries()['totals']['profit'];
$beforeCredits = ContractFlightAgentReceipts::allocate($agents->pluck('id'))['credits'];
$check($beforeFunds['ok'] ?? false, 'Company profit must be available.');
DB::beginTransaction();

try {
    $holder = (array) DB::table('passport_holders')->where('id', $template->passport_holder_id)->first();
    unset($holder['id']);
    $holder['passport_no'] = 'BAGCF'.strtoupper(bin2hex(random_bytes(4)));
    $holderId = DB::table('passport_holders')->insertGetId($holder);
    $ticket = $template->getAttributes();
    unset($ticket['id']);
    $ticketId = DB::table('ticket_purchases')->insertGetId(array_merge($ticket, [
        'passport_holder_id' => $holderId, 'ticket_no' => 'BAGCF-'.bin2hex(random_bytes(5)),
        'amount' => 10000, 'vendor_id' => $vendor->id, 'portal_id' => null,
        'paid_amount' => 0, 'due_amount' => 10000, 'deleted_at' => null,
        'free_baggage_kg' => null, 'extra_baggage_kg' => null, 'extra_baggage_cost' => null,
        'extra_baggage_agent_charge' => null, 'extra_baggage_bearer' => null,
    ]));
    $baggage = ['free_baggage_kg' => 30, 'extra_baggage_kg' => 10, 'extra_baggage_cost' => 1000,
        'extra_baggage_agent_charge' => 1300, 'extra_baggage_bearer' => 'customer'];
    $row = [
        'passport_holder_id' => $holderId, 'agent_id' => $agents[0]->id,
        'agent_price' => 15000, 'ticket_purchase_ids' => [$ticketId],
        'ticket_baggage' => [$ticketId => $baggage],
    ];
    $request = Request::create('/', 'POST', ['status' => 'open', 'ticket_id' => $template->ticket_id, 'passengers' => [$row]]);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $controller = app(ManageFlightController::class);
    $response = $controller->store($request)->getData(true);
    $check($response['success'] ?? false, 'Flight save failed: '.($response['message'] ?? 'unknown'));
    $p = ContractFlightPassenger::where('passport_holder_id', $holderId)->with('ticketPurchases')->firstOrFail();
    $flight = ContractFlight::findOrFail($p->contract_flight_id);
    $net = fn ($type, $party) => Transaction::where('type', $type)->where('invoice_id', $ticketId)->where('user_id', $party)
        ->get()->sum(fn ($tx) => $tx->debit - $tx->credit);
    $check($near($net(TicketBaggage::COST, $vendor->id), 1000), 'Baggage cost must reach the ticket vendor.');
    $check($near($net(TicketBaggage::CHARGE, $agents[0]->id), 1300), 'Baggage charge must reach the flight passenger agent.');
    $check($near($p->agent_total, 16300), 'Passenger charge must be package 15000 plus baggage 1300.');
    $check($near($p->total_travel_cost, 11000), 'Passenger cost must be ticket 10000 plus baggage 1000.');
    $check($near($p->profit, 5300), 'Passenger profit must include baggage profit 300.');
    $check($near($flight->revenue, 16300), 'Stored flight revenue must include baggage charge.');
    $check($near($flight->cost_price, 11000), 'Stored flight cost must include baggage cost.');
    $check($near(CompanyLedger::entries()['totals']['profit'] - $beforeLedger, 300), 'Company Ledger must show baggage profit 300.');
    $check($near(TicketingProfit::compute()['totals']['profit'], $beforeDept), 'Linked baggage must not be counted again as Ticketing department profit.');
    $afterFunds = CompanyFunds::summary();
    $check($near($afterFunds['totals']['profit'] - $beforeFunds['totals']['profit'], 5300), 'Overall company profit must count ticket plus baggage profit exactly once.');

    $count = Transaction::whereIn('type', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $ticketId)->count();
    app(TicketBaggage::class)->forPassenger($p, [$ticketId => $baggage]);
    $check(Transaction::whereIn('type', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $ticketId)->count() === $count, 'Repeat passenger save must not duplicate baggage.');
    $linkRequest = Request::create('/', 'POST', ['ticket_purchase_id' => $ticketId]);
    $linkRequest->setUserResolver(fn () => $actor);
    $app->instance('request', $linkRequest);
    $linked = app(ContractFlightPassengerController::class)->linkTicket($linkRequest, 'super-admin', $p)->getData(true);
    $check($linked['success'] ?? false, 'Individual ticket link must support baggage.');
    $check(Transaction::whereIn('type', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $ticketId)->count() === $count, 'Relinking must not duplicate baggage.');

    $row['agent_id'] = $agents[1]->id;
    $row['ticket_baggage'][$ticketId]['extra_baggage_cost'] = 900;
    $row['ticket_baggage'][$ticketId]['extra_baggage_agent_charge'] = 1250;
    $request = Request::create('/', 'PUT', ['status' => 'open', 'ticket_id' => $template->ticket_id, 'passengers' => [$row]]);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $response = $controller->update($request, 'super-admin', $flight->id)->getData(true);
    $check($response['success'] ?? false, 'Flight edit failed: '.($response['message'] ?? 'unknown'));
    $check($near($net(TicketBaggage::CHARGE, $agents[0]->id), 0), 'Old agent must receive a baggage credit.');
    $check($near($net(TicketBaggage::CHARGE, $agents[1]->id), 1250), 'Baggage must follow the edited passenger agent.');
    $check($near($net(TicketBaggage::COST, $vendor->id), 900), 'Vendor must receive only the baggage cost adjustment.');
    $p->refresh()->load('ticketPurchases');
    $check($near($p->profit, 5350), 'Edited passenger profit must be 5350.');

    $row['ticket_baggage'][$ticketId]['extra_baggage_bearer'] = 'company';
    $row['ticket_baggage'][$ticketId]['extra_baggage_agent_charge'] = 0;
    $request = Request::create('/', 'PUT', ['status' => 'open', 'ticket_id' => $template->ticket_id, 'passengers' => [$row]]);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $response = $controller->update($request, 'super-admin', $flight->id)->getData(true);
    $check($response['success'] ?? false, 'Company-paid baggage edit failed: '.($response['message'] ?? 'unknown'));
    $check($near($net(TicketBaggage::CHARGE, $agents[1]->id), 0), 'Company-paid baggage must leave no agent charge.');
    $p->refresh()->load('ticketPurchases');
    $check($near($p->profit, 4100), 'Company-paid baggage must reduce passenger profit by 900.');
    $afterCredits = ContractFlightAgentReceipts::allocate($agents->pluck('id'))['credits'];
    foreach ($agents as $agent) {
        $check($near($afterCredits[$agent->id] ?? 0, $beforeCredits[$agent->id] ?? 0), 'Baggage corrections must never become cash receipts.');
    }

    $row['ticket_baggage'] = [$template->id => $baggage];
    $request = Request::create('/', 'PUT', ['status' => 'open', 'ticket_id' => $template->ticket_id, 'passengers' => [$row]]);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $response = $controller->update($request, 'super-admin', $flight->id)->getData(true);
    $check(empty($response['success']), 'Another passenger ticket must not accept baggage edits.');
    $check($near(TicketPurchase::findOrFail($ticketId)->extra_baggage_cost, 900), 'Rejected save must preserve the original baggage cost.');

    foreach (JournalEntry::whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('source_id', $ticketId)->pluck('id') as $journalId) {
        $lines = JournalItem::where('journal_entry_id', $journalId)->get();
        $check($near($lines->sum('debit'), $lines->sum('credit')), 'Every baggage journal must balance.');
    }
    View::share('role', 'super-admin');
    $route = new Route(['GET'], '/{role}/contract-flights/dashboard', fn () => null);
    $route->bind(Request::create('/super-admin/contract-flights/dashboard'));
    $request->setRouteResolver(fn () => $route);
    $html = $controller->dashboard()->render();
    $check(str_contains($html, 'pb-nt-bag-cost'), 'Contract Flight must render extra baggage cost input.');
    $check(str_contains($html, 'pb-nt-bag-bearer'), 'Contract Flight must render the baggage payer.');
    $check(str_contains($html, '[ticket_baggage]['), 'Builder must submit baggage per linked ticket.');
    file_put_contents(sys_get_temp_dir().'/epal-cf-baggage.html', $html);
    echo "Contract Flight baggage checks passed: {$checks}\n";
} finally {
    DB::rollBack();
}
