<?php

use App\Http\Controllers\Dashboard\TicketAccountsController;
use App\Http\Controllers\Dashboard\TicketDirectSaleController;
use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Http\Controllers\Dashboard\TicketSaleController;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyLedger;
use App\Services\TicketBaggage;
use App\Services\TicketingProfit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Explicit local integration check. Every fixture and posting rolls back; no notifications leave the process.
Queue::fake();
config(['services.party_ledger.notify' => false]);
$actor = User::where('is_super_admin', 1)->firstOrFail();
auth()->setUser($actor);
Gate::before(fn () => true);
$checks = 0;
$assert = function (bool $condition, string $message) use (&$checks) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$near = fn ($actual, $expected) => abs((float) $actual - $expected) < 0.005;
$service = app(TicketBaggage::class);
$vendors = User::role('vendor')->limit(2)->get();
$agents = User::role('agent')->limit(2)->get();
$template = TicketPurchase::where('company_id', 2)->whereNotNull('vendor_id')->where('status', 'confirm')->firstOrFail();
$saleTemplate = (array) DB::table('ticket_sales')->where('status', 'confirm')->first();
$portal = Portal::whereNotNull('account_id')->firstOrFail();
$portalOpening = (float) $portal->balance;
$assert($vendors->count() === 2 && $agents->count() === 2, 'Two vendors and agents are required.');
$beforeProfit = TicketingProfit::compute()['totals']['profit'];
$beforeCompany = CompanyLedger::entries()['totals']['profit'];
DB::beginTransaction();

try {
    $data = $template->getAttributes();
    unset($data['id']);
    $data = array_merge($data, [
        'ticket_no' => 'BAG-TEST-'.bin2hex(random_bytes(5)), 'company_id' => 2,
        'vendor_id' => $vendors[0]->id, 'portal_id' => null, 'amount' => 10000,
        'paid_amount' => 0, 'due_amount' => 10000, 'payment_status' => 'due',
        'purchase_date' => now()->toDateString(), 'deleted_at' => null,
        'free_baggage_kg' => 30, 'extra_baggage_kg' => 5, 'extra_baggage_cost' => 500,
        'extra_baggage_agent_charge' => 700, 'extra_baggage_bearer' => 'customer', 'baggage_conditions' => 'Non-refundable extra baggage',
    ]);
    $id = DB::table('ticket_purchases')->insertGetId($data);
    unset($saleTemplate['id']);
    $saleId = DB::table('ticket_sales')->insertGetId(array_merge($saleTemplate, [
        'invoice_no' => 'BAG-TEST-'.bin2hex(random_bytes(5)), 'client_id' => $agents[0]->id,
        'company_id' => 2, 'total_amount' => 15000, 'paid_amount' => 0, 'due_amount' => 15000,
        'sale_date' => now()->toDateString(), 'deleted_at' => null,
    ]));
    DB::table('ticket_sale_items')->insert(['ticket_sale_id' => $saleId, 'ticket_purchase_id' => $id, 'price' => 15000]);
    $ticket = TicketPurchase::findOrFail($id);
    $net = fn ($type, $party) => Transaction::where('type', $type)->where('invoice_id', $id)->where('user_id', $party)
        ->get()->sum(fn ($r) => $r->debit - $r->credit);
    $set = function (array $changes) use ($ticket, $service) {
        DB::table('ticket_purchases')->where('id', $ticket->id)->update($changes);
        $service->sync($ticket->refresh());
    };
    $service->sync($ticket);
    $assert($near($net(TicketBaggage::COST, $vendors[0]->id), 500), 'Vendor baggage cost must be 500.');
    $assert($near($net(TicketBaggage::CHARGE, $agents[0]->id), 700), 'Buying agent must be charged 700.');
    $assert($near(TicketingProfit::compute()['totals']['profit'] - $beforeProfit, 5200), 'Company profit must include ticket profit 5000 plus baggage profit 200.');
    $assert($near(CompanyLedger::entries()['totals']['profit'] - $beforeCompany, 200), 'Company Ledger must include baggage profit 200.');
    $count = Transaction::whereIn('type', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $id)->count();
    $service->sync($ticket);
    $assert(Transaction::whereIn('type', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('invoice_id', $id)->count() === $count, 'Repeat save must not duplicate postings.');

    $set(['extra_baggage_cost' => 400, 'extra_baggage_agent_charge' => 650]);
    $assert($near($net(TicketBaggage::COST, $vendors[0]->id), 400), 'Cost edit must post only the difference.');
    $assert($near($net(TicketBaggage::CHARGE, $agents[0]->id), 650), 'Charge edit must post only the difference.');
    $set(['vendor_id' => $vendors[1]->id]);
    $assert($near($net(TicketBaggage::COST, $vendors[0]->id), 0), 'Old vendor must be credited on seller change.');
    $assert($near($net(TicketBaggage::COST, $vendors[1]->id), 400), 'New vendor must receive the baggage cost.');
    DB::table('ticket_sales')->where('id', $saleId)->update(['client_id' => $agents[1]->id]);
    $service->sync($ticket);
    $assert($near($net(TicketBaggage::CHARGE, $agents[0]->id), 0), 'Old buyer must be credited on buyer change.');
    $assert($near($net(TicketBaggage::CHARGE, $agents[1]->id), 650), 'New ticket buyer must receive the charge.');

    $set(['extra_baggage_bearer' => 'company', 'extra_baggage_agent_charge' => 0]);
    $assert($near($net(TicketBaggage::CHARGE, $agents[1]->id), 0), 'Company-paid baggage must leave no net agent charge.');
    $assert($near(TicketingProfit::compute()['totals']['profit'] - $beforeProfit, 4600), 'Company-paid cost must reduce company profit by 400.');
    $set(['vendor_id' => null, 'portal_id' => $portal->id]);
    $assert($near($net(TicketBaggage::COST, $vendors[1]->id), 0), 'Switch to portal must credit the vendor.');
    $assert($near($portal->refresh()->balance, $portalOpening - 400), 'Portal wallet must pay baggage cost.');
    $set(['extra_baggage_cost' => 350]);
    $assert($near($portal->refresh()->balance, $portalOpening - 350), 'Reduced portal cost must refund the difference.');
    $set(['vendor_id' => $vendors[0]->id, 'portal_id' => null]);
    $assert($near($portal->refresh()->balance, $portalOpening), 'Switch back to vendor must restore the portal wallet.');
    $assert($near($net(TicketBaggage::COST, $vendors[0]->id), 350), 'Vendor must receive the restored baggage cost.');

    foreach ([['extra_baggage_bearer' => null], ['extra_baggage_bearer' => 'company', 'extra_baggage_agent_charge' => 20], ['extra_baggage_kg' => 0]] as $invalid) {
        $saved = $ticket->getAttributes();
        try {
            $set($invalid);
            throw new RuntimeException('Invalid baggage terms were accepted.');
        } catch (ValidationException) {
            $assert(true, 'Invalid baggage terms rejected.');
        } finally {
            DB::table('ticket_purchases')->where('id', $id)->update($saved);
            $ticket->refresh();
        }
    }

    // Exercise the actual entry endpoint: purchase, sale and baggage share one transaction.
    $entry = [
        'passport_holder_id' => $ticket->passport_holder_id, 'ticket_id' => $ticket->ticket_id,
        'vendor_id' => $vendors[0]->id, 'ticket_no' => 'BAG-ENTRY-'.bin2hex(random_bytes(4)),
        'cost_amount' => 10000, 'purchase_paid' => 0, 'status' => 'purchased',
        'sale_client_id' => $agents[0]->id, 'sale_price' => 15000, 'free_baggage_kg' => 30,
        'extra_baggage_kg' => 10, 'extra_baggage_cost' => 1000, 'extra_baggage_agent_charge' => 1300,
        'extra_baggage_bearer' => 'customer', 'baggage_conditions' => 'Test conditions',
    ];
    $request = Request::create('/', 'POST', ['passengers' => [$entry], 'currency' => 'BDT']);
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $response = app(TicketDirectSaleController::class)->store($request, 'super-admin')->getData(true);
    $assert(($response['success'] ?? false) === true, 'Ticket Entry failed: '.($response['message'] ?? 'unknown'));
    $created = TicketPurchase::where('ticket_no', $entry['ticket_no'])->firstOrFail();
    $assert($near($created->free_baggage_kg, 30), 'Free baggage KG must persist.');
    $assert($created->baggage_conditions === 'Test conditions', 'Baggage conditions must persist.');
    $assert(Transaction::where('type', TicketBaggage::CHARGE)->where('invoice_id', $created->id)->where('user_id', $agents[0]->id)->where('debit', 1300)->exists(), 'Ticket Entry must charge the ticket buyer.');
    $assert(Transaction::where('type', TicketBaggage::COST)->where('invoice_id', $created->id)->where('user_id', $vendors[0]->id)->where('debit', 1000)->exists(), 'Ticket Entry must post seller baggage cost.');

    $editRequest = Request::create('/', 'PUT', [
        'passport_holder_id' => $created->passport_holder_id, 'ticket_id' => $created->ticket_id,
        'ticket_no' => $created->ticket_no, 'ticket_type' => 'air', 'trip_type' => 'one-way',
        'vendor_id' => $created->vendor_id, 'portal_id' => null, 'status' => 'confirm',
        'amount' => 10000, 'currency' => 'BDT', 'purchase_date' => now()->toDateString(),
        'free_baggage_kg' => 35, 'extra_baggage_kg' => 10, 'extra_baggage_cost' => 900,
        'extra_baggage_agent_charge' => 1200, 'extra_baggage_bearer' => 'customer',
    ]);
    $editRequest->setUserResolver(fn () => $actor);
    $app->instance('request', $editRequest);
    $edited = app(TicketPurchaseController::class)->update($editRequest, 'super-admin', $created->id)->getData(true);
    $assert(($edited['success'] ?? false) === true, 'Ticket edit failed: '.($edited['message'] ?? 'unknown'));
    $assert($near($created->refresh()->free_baggage_kg, 35), 'Ticket edit must persist free baggage KG.');
    $figures = app(TicketAccountsController::class)->figures(Request::create('/', 'GET', ['company' => 2]));
    $ticketFigure = $figures['tickets']->firstWhere('id', $created->id);
    $assert($near($ticketFigure['cost'], 10900), 'Ticket Accounts must include baggage cost.');
    $assert($near($ticketFigure['due'], 10900), 'Ticket Accounts supplier due must include baggage cost.');
    $assert($near($figures['changes']->where('kind', 'baggage')->where('pnr', $created->ticket_no)->sum('revenue'), 1200), 'Ticket Accounts must include net baggage revenue.');
    $assert($near($figures['changes']->where('kind', 'baggage')->where('pnr', $created->ticket_no)->sum('sold_cost'), 900), 'Ticket Accounts must include net baggage cost exactly once.');
    $createdSaleId = DB::table('ticket_sale_items')->where('ticket_purchase_id', $created->id)->value('ticket_sale_id');
    $saleRequest = Request::create('/', 'PUT', [
        'client_id' => $agents[1]->id, 'status' => 'confirm', 'currency' => 'BDT',
        'sale_date' => now()->toDateString(), 'ticket_purchase_ids' => [$created->id],
        'sale_prices' => [$created->id => 15000], 'paid_amount' => 0,
        'payment_method' => 'cash', 'payment_status' => 'due',
    ]);
    $saleRequest->setUserResolver(fn () => $actor);
    $app->instance('request', $saleRequest);
    $saleResponse = app(TicketSaleController::class)->update($saleRequest, 'super-admin', TicketSale::findOrFail($createdSaleId))->getData(true);
    $assert(($saleResponse['success'] ?? false) === true, 'Manage Sales buyer change failed: '.($saleResponse['message'] ?? 'unknown'));
    $buyerNet = fn ($party) => Transaction::where('type', TicketBaggage::CHARGE)->where('invoice_id', $created->id)->where('user_id', $party)
        ->get()->sum(fn ($r) => $r->debit - $r->credit);
    $assert($near($buyerNet($agents[0]->id), 0), 'Manage Sales must credit baggage to the previous buyer.');
    $assert($near($buyerNet($agents[1]->id), 1200), 'Manage Sales must charge baggage to the new ticket buyer.');

    $invalidEntry = $entry;
    unset($invalidEntry['sale_client_id'], $invalidEntry['sale_price']);
    $invalidEntry['ticket_no'] = 'BAG-INVALID-'.bin2hex(random_bytes(4));
    $invalidRequest = Request::create('/', 'POST', ['passengers' => [$invalidEntry], 'currency' => 'BDT']);
    $invalidRequest->setUserResolver(fn () => $actor);
    $app->instance('request', $invalidRequest);
    $invalidResponse = app(TicketDirectSaleController::class)->store($invalidRequest, 'super-admin')->getData(true);
    $assert(empty($invalidResponse['success']), 'Agent-paid baggage without the ticket buyer must be rejected.');
    $assert(! TicketPurchase::where('ticket_no', $invalidEntry['ticket_no'])->exists(), 'Invalid entry must roll back its purchase and postings.');
    $invalidEntry['extra_baggage_bearer'] = 'company';
    $invalidEntry['extra_baggage_agent_charge'] = 0;
    $invalidRequest = Request::create('/', 'POST', ['passengers' => [$invalidEntry], 'currency' => 'BDT']);
    $invalidRequest->setUserResolver(fn () => $actor);
    $app->instance('request', $invalidRequest);
    $companyResponse = app(TicketDirectSaleController::class)->store($invalidRequest, 'super-admin')->getData(true);
    $assert(($companyResponse['success'] ?? false) === true, 'Company-paid baggage must work on an unsold ticket.');
    $companyTicket = TicketPurchase::where('ticket_no', $invalidEntry['ticket_no'])->firstOrFail();
    $assert(! Transaction::where('type', TicketBaggage::CHARGE)->where('invoice_id', $companyTicket->id)->exists(), 'Company-paid entry must never charge an agent.');

    $app->instance('request', $editRequest);

    View::share('role', 'super-admin');
    $route = new Route(['GET'], '/{role}', fn () => null);
    $route->bind(Request::create('/super-admin'));
    $editRequest->setRouteResolver(fn () => $route);
    $html = app(TicketDirectSaleController::class)->create(Request::create('/', 'GET'))->render();
    $assert(str_contains($html, 'passengers[0][extra_baggage_cost]'), 'Entry form must render baggage fields.');
    $assert(str_contains($html, 'passengers[${idx}][extra_baggage_cost]'), 'New passenger card template must render baggage fields.');
    file_put_contents(sys_get_temp_dir().'/epal-baggage-create.html', $html);
    $html = app(TicketDirectSaleController::class)->edit('super-admin', $created->id)->render();
    $assert(str_contains($html, 'name="extra_baggage_cost"'), 'Edit form must render baggage fields.');
    file_put_contents(sys_get_temp_dir().'/epal-baggage-edit.html', $html);

    $journalIds = JournalEntry::whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE])->whereIn('source_id', [$id, $created->id])->pluck('id');
    foreach ($journalIds as $journalId) {
        $items = JournalItem::where('journal_entry_id', $journalId)->get();
        $assert($near($items->sum('debit'), $items->sum('credit')), 'Baggage journal must balance.');
    }
    foreach ($vendors->merge($agents)->pluck('id')->unique() as $party) {
        $rows = Transaction::where('company_id', 2)->where('user_id', $party)->orderBy('id')->get();
        foreach ($rows as $index => $row) {
            if (! in_array($row->type, [TicketBaggage::COST, TicketBaggage::CHARGE], true)) {
                continue;
            }
            $assert($near($row->balance, $row->old_balance + $row->debit - $row->credit), 'Baggage running balance must match its movement.');
            $assert($near($row->old_balance, $index > 0 ? $rows[$index - 1]->balance : 0), 'Baggage ledger chain must continue from the previous row.');
        }
    }
    echo "Ticket baggage checks passed: {$checks}\n";
} finally {
    DB::rollBack();
}
