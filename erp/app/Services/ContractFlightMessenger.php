<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ContractFlight;
use App\Models\ContractFlightMessage;
use App\Models\ContractFlightMessageRule;
use App\Models\ContractFlightPassenger;
use App\Support\TravelCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Contract Flight messaging — every notice a passenger or their agent gets
 * about a batch, whether someone pressed Send on the Messages page or the
 * pipeline moved and a rule said to tell them.
 *
 * One vocabulary of scenarios (SCENARIOS), one set of placeholders
 * (PLACEHOLDERS), one log (contract_flight_messages). The wording per
 * scenario lives in contract_flight_message_rules so it can be edited on
 * screen; the constants below are the defaults the migration seeds and
 * the fallback if a rule row is missing.
 *
 * Rules that must hold:
 * - Automatic sends never throw into the caller. A gateway that is down
 *   costs the notice, never the stage change that triggered it.
 * - Passengers get SMS only (passport holders carry a phone, not an
 *   email); agents get SMS and email. WhatsApp is never sent from the
 *   server — wa.me can only be opened by a person — so the page prepares
 *   the link and the log records it as 'prepared'.
 * - English SMS: a Bangla SMS bills in 70-character segments.
 */
class ContractFlightMessenger
{
    public const PLACEHOLDERS = [
        '{passenger}' => "Passenger's name", '{passport}' => 'Passport number', '{agent}' => "Agent's name",
        '{flight}' => 'Batch number (CF-…)', '{flight_line}' => 'Batch · airline & flight no · route · departure — only the parts that are known',
        '{airline}' => 'Airline', '{airline_flight}' => "Airline's flight number",
        '{route}' => 'DAC → BKK', '{from}' => 'Origin airport', '{to}' => 'Destination airport',
        '{departure}' => 'Departure date & time', '{arrival}' => 'Arrival date & time', '{stage}' => 'Current stage',
        '{pnr}' => 'PNR / ticket number(s)', '{due}' => "Agent's due for this passenger", '{company}' => 'Our company name',
        '{note}' => 'Free text typed when sending (delay reason, new time…)',
    ];

    /**
     * scenario => label, group, auto (on by default), to_passenger, to_agent, passenger text, agent text.
     * Groups: pipeline (fires on stage changes), flight (schedule events),
     * money (agent side), manual (only ever sent by hand).
     */
    public const SCENARIOS = [
        'passenger_received' => ['label' => 'Passenger registered', 'group' => 'pipeline', 'auto' => false, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'passenger_received',
            'passenger' => 'Dear {passenger}, {company} has registered you on {flight_line}. We will keep you informed at every step. Agent: {agent}.',
            'agent' => 'Dear {agent}, {passenger} ({passport}) is registered on {flight_line}. - {company}'],
        'documents_received' => ['label' => 'Documents received', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'documents_received',
            'passenger' => 'Dear {passenger}, we have received your documents for {flight_line}. They are now being verified. - {company}',
            'agent' => 'Dear {agent}, documents received for {passenger} ({passport}) on {flight_line}. - {company}'],
        'documents_verified' => ['label' => 'Documents verified', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'documents_verified',
            'passenger' => 'Dear {passenger}, your documents for {flight_line} have been verified. Next: ticketing. - {company}',
            'agent' => 'Dear {agent}, documents verified for {passenger} ({passport}) on {flight_line}. - {company}'],
        'ticket_issued' => ['label' => 'Ticket issued', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'ticket_issued',
            'passenger' => 'Dear {passenger}, your ticket is issued. PNR {pnr}. {flight_line}. - {company}',
            'agent' => 'Dear {agent}, ticket issued for {passenger}: PNR {pnr}. {flight_line}. - {company}'],
        'ready_for_travel' => ['label' => 'Ready for travel', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'ready_for_travel',
            'passenger' => 'Dear {passenger}, everything is ready: {flight_line}. Please be at {from} 4 hours before departure with your passport. - {company}',
            'agent' => 'Dear {agent}, {passenger} is ready for travel: {flight_line}. - {company}'],
        'confirmation_call' => ['label' => 'Confirmation call done', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'confirmation_call',
            'passenger' => 'Dear {passenger}, as confirmed on the phone: {flight_line}. Report at {from} 4 hours before departure with your passport, ticket (PNR {pnr}) and documents. - {company}',
            'agent' => 'Dear {agent}, {passenger} has been called and confirmed for {flight_line}. - {company}'],
        'boarding' => ['label' => 'Boarding', 'group' => 'pipeline', 'auto' => false, 'to_passenger' => false, 'to_agent' => true, 'stage' => 'boarding',
            'passenger' => 'Dear {passenger}, you are now boarding {flight_line}. Safe travels! - {company}',
            'agent' => 'Dear {agent}, {passenger} has boarded {flight_line}. - {company}'],
        'departed' => ['label' => 'Departed', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => false, 'to_agent' => true, 'stage' => 'departed',
            'passenger' => 'Dear {passenger}, {flight_line} has departed. Arrival at {to} expected {arrival}. - {company}',
            'agent' => 'Dear {agent}, {passenger} departed: {flight_line}. Arrival expected {arrival}. - {company}'],
        'landed' => ['label' => 'Landed', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => false, 'to_agent' => true, 'stage' => 'landed',
            'passenger' => 'Dear {passenger}, welcome to {to}. Please proceed to immigration with your documents. - {company}',
            'agent' => 'Dear {agent}, {passenger} has landed at {to}. {flight_line}. - {company}'],
        'arrival_confirmed' => ['label' => 'Arrival confirmed', 'group' => 'pipeline', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => 'arrival_confirmed',
            'passenger' => 'Dear {passenger}, your arrival at {to} is confirmed. Thank you for travelling with {company}.',
            'agent' => 'Dear {agent}, arrival confirmed for {passenger} at {to}. {flight_line}. - {company}'],
        'stage_update' => ['label' => 'Other stage update', 'group' => 'pipeline', 'auto' => false, 'to_passenger' => false, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, update on {flight_line}: your status is now "{stage}". - {company}',
            'agent' => 'Dear {agent}, {passenger} on {flight_line} moved to "{stage}". - {company}'],

        'flight_schedule' => ['label' => 'Flight schedule', 'group' => 'flight', 'auto' => false, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, your flight: {flight_line}. Arrives {arrival}. Report at {from} 4 hours early. - {company}',
            'agent' => 'Dear {agent}, schedule for {passenger}: {flight_line}. Arrives {arrival}. - {company}'],
        'rescheduled' => ['label' => 'Rescheduled / time changed', 'group' => 'flight', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, IMPORTANT: {flight_line} is rescheduled. New departure: {departure}. {note} - {company}',
            'agent' => 'Dear {agent}, {passenger} on {flight_line} is rescheduled. New departure: {departure}. {note} - {company}'],
        'delay' => ['label' => 'Delay notice', 'group' => 'flight', 'auto' => false, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, your flight {flight_line} is delayed. {note} We will update you. - {company}',
            'agent' => 'Dear {agent}, {flight_line} carrying {passenger} is delayed. {note} - {company}'],
        'flight_cancelled' => ['label' => 'Flight cancelled', 'group' => 'flight', 'auto' => true, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, we regret that {flight_line} is cancelled. {note} We will contact you about the next steps. - {company}',
            'agent' => 'Dear {agent}, {flight_line} for {passenger} is cancelled. {note} - {company}'],
        'pre_departure' => ['label' => 'Pre-departure reminder', 'group' => 'flight', 'auto' => false, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, reminder: {flight_line}, from {from}. Bring your passport, ticket (PNR {pnr}) and documents. Be there 4 hours early. - {company}',
            'agent' => 'Dear {agent}, reminder: {passenger} flies {flight_line}. - {company}'],
        'offloaded' => ['label' => 'Offloaded / boarding denied', 'group' => 'flight', 'auto' => true, 'to_passenger' => false, 'to_agent' => true, 'stage' => null,
            'passenger' => 'Dear {passenger}, you could not board {flight_line}. {note} Please contact us. - {company}',
            'agent' => 'Dear {agent}, {passenger} was offloaded from {flight_line}. {note} - {company}'],

        'payment_reminder' => ['label' => 'Payment reminder (agent)', 'group' => 'money', 'auto' => false, 'to_passenger' => false, 'to_agent' => true, 'stage' => null,
            'passenger' => '',
            'agent' => 'Dear {agent}, {due} is due for {passenger} on {flight_line}. Please arrange payment. - {company}'],
        'payment_received' => ['label' => 'Payment received (agent)', 'group' => 'money', 'auto' => false, 'to_passenger' => false, 'to_agent' => true, 'stage' => null,
            'passenger' => '',
            'agent' => 'Dear {agent}, thank you, your payment has been received. {note} - {company}'],

        'custom' => ['label' => 'Custom message', 'group' => 'manual', 'auto' => false, 'to_passenger' => true, 'to_agent' => true, 'stage' => null,
            'passenger' => '{note}',
            'agent' => '{note}'],
    ];

    /** Scenario → exception flag that fires it. */
    public const FLAG_SCENARIOS = [
        'rescheduled' => 'rescheduled', 'time_changed' => 'rescheduled',
        'flight_cancelled' => 'flight_cancelled', 'flight_missed' => 'offloaded',
        'boarding_denied' => 'offloaded', 'immigration_offload' => 'offloaded', 'deported' => 'offloaded', 'deported_we_bear' => 'offloaded',
    ];

    // ── Rules ───────────────────────────────────────────────────────────

    public static function installed(): bool
    {
        try {
            return Schema::hasTable('contract_flight_message_rules') && Schema::hasTable('contract_flight_messages');
        } catch (\Throwable) {
            return false;
        }
    }

    /** The rule for a scenario, from the table when present, else the seeded default. */
    public static function rule(string $scenario): array
    {
        $default = self::SCENARIOS[$scenario] ?? self::SCENARIOS['custom'];
        $row = self::installed() ? ContractFlightMessageRule::where('scenario', $scenario)->first() : null;

        return [
            'scenario' => $scenario,
            'label' => $default['label'],
            'group' => $default['group'],
            'auto' => $row ? $row->auto : $default['auto'],
            'to_passenger' => $row ? $row->to_passenger : $default['to_passenger'],
            'to_agent' => $row ? $row->to_agent : $default['to_agent'],
            'via_sms' => $row ? $row->via_sms : true,
            'via_email' => $row ? $row->via_email : true,
            'passenger_template' => $row?->passenger_template ?? $default['passenger'],
            'agent_template' => $row?->agent_template ?? $default['agent'],
        ];
    }

    public static function rules(): array
    {
        return array_map(fn ($key) => self::rule($key), array_keys(self::SCENARIOS));
    }

    // ── Rendering ───────────────────────────────────────────────────────

    /** Everything a template can mention, for one passenger. */
    public static function context(ContractFlightPassenger $p, ?string $note = null): array
    {
        $p->loadMissing(['contractFlight.ticket.airline', 'contractFlight.ticket.from_airport', 'contractFlight.ticket.to_airport', 'contractFlight.creator:id,company_id', 'agent', 'passportHolder', 'ticketPurchases']);
        $f = $p->contractFlight;
        $fmt = fn ($d) => $d ? $d->format('d M Y, h:i A') : 'TBA';
        $from = $f?->ticket?->from_airport;
        $to = $f?->ticket?->to_airport;
        $pnrs = $p->ticketPurchases->where('status', '!=', 'cancelled')->pluck('ticket_no')->filter()->implode(', ');
        $due = max(0, $p->chargedPackage() - (float) ($p->agent_paid_amount ?? 0));

        // Whatever the batch already knows about itself, in one line, so a
        // message never reads "the airline (— → —)" for a flight whose route
        // or carrier has not been entered yet.
        $airline = trim(($f?->airline?->name ?? '').' '.($f?->airline_flight_no ?? ''));
        $route = ($from?->code && $to?->code) ? "{$from->code} → {$to->code}" : null;
        $flightLine = implode(' · ', array_filter([$f?->flight_number, $airline ?: null, $route, $f?->departure_at ? $fmt($f->departure_at) : null]));

        return [
            '{passenger}' => $p->passportHolder?->name ?? 'Passenger',
            '{passport}' => $p->passportHolder?->passport_no ?? '—',
            '{agent}' => $p->agent?->name ?? 'Agent',
            '{flight}' => $f?->flight_number ?? 'CF',
            '{flight_line}' => $flightLine ?: 'your contract flight',
            '{airline}' => $f?->airline?->name ?? '',
            '{airline_flight}' => $f?->airline_flight_no ?? '',
            '{route}' => $route ?? 'route to be confirmed',
            '{from}' => $from?->name ?? 'the departure airport',
            '{to}' => $to?->name ?? 'the destination',
            '{departure}' => $fmt($f?->departure_at),
            '{arrival}' => $fmt($f?->arrival_at),
            '{stage}' => ContractFlightPassenger::stageLabel($p->stage),
            '{pnr}' => $pnrs ?: 'pending',
            '{due}' => 'BDT '.number_format($due),
            '{company}' => self::companyName($f),
            '{note}' => trim((string) $note),
        ];
    }

    /**
     * The company a message signs off as: the one the batch was created
     * under (contract flights are Travels, company 2), never simply the
     * first row of the companies table. The FULL name — the owner wants
     * "EPAL TRAVELS & CONSULTANCY" spelled out, not the short form.
     * Cached per request.
     */
    private static array $companyNames = [];

    public static function companyName(?ContractFlight $f): string
    {
        $id = TravelCompany::ID;
        if (! isset(self::$companyNames[$id])) {
            $company = Company::find($id) ?? Company::find(2) ?? Company::first();
            self::$companyNames[$id] = $company?->name ?: config('app.name');
        }

        return self::$companyNames[$id];
    }

    public static function render(string $template, array $context): string
    {
        $text = strtr($template, $context);

        // A template that used {note} with nothing typed leaves a double space or a dangling " ." — tidy it.
        return trim(preg_replace(['/\(\s*\)/', '/\s{2,}/', '/\s+([.,])/'], ['', ' ', '$1'], $text));
    }

    // ── Recipients ──────────────────────────────────────────────────────

    /**
     * The two people a passenger's notice can go to. Passengers: SMS only
     * (a passport holder has a phone, not an email). Agents: SMS + email.
     */
    public static function recipients(ContractFlightPassenger $p, array $rule): array
    {
        $out = [];
        if ($rule['to_passenger'] && $rule['passenger_template'] !== '') {
            $out[] = ['type' => 'passenger', 'id' => $p->passport_holder_id, 'name' => $p->passportHolder?->name ?? 'Passenger',
                'phone' => $p->phone, 'email' => null, 'template' => $rule['passenger_template']];
        }
        if ($rule['to_agent'] && $p->agent && $rule['agent_template'] !== '') {
            $out[] = ['type' => 'agent', 'id' => $p->agent_id, 'name' => $p->agent->name,
                'phone' => $p->agent->phone, 'email' => $p->agent->email, 'template' => $rule['agent_template']];
        }

        return $out;
    }

    // ── Sending ─────────────────────────────────────────────────────────

    /**
     * Send one scenario for one passenger to whoever the rule names, on the
     * channels the rule allows (intersected with $channels when given).
     * Returns the log rows. Never throws.
     */
    public static function send(string $scenario, ContractFlightPassenger $p, array $opts = []): array
    {
        $rule = self::rule($scenario);
        $note = $opts['note'] ?? null;
        $trigger = $opts['trigger'] ?? 'manual';
        $channels = $opts['channels'] ?? ['sms', 'email'];
        $only = $opts['only'] ?? null;              // 'passenger' | 'agent' | null
        $override = $opts['template'] ?? null;      // custom wording typed on the page
        $subject = $opts['subject'] ?? ($rule['label'].' — '.($p->contractFlight?->flight_number ?? 'Contract flight'));
        $context = self::context($p, $note);
        $logs = [];

        foreach (self::recipients($p, $rule) as $r) {
            if ($only && $r['type'] !== $only) {
                continue;
            }
            $body = self::render($override ?: $r['template'], $context);
            if ($body === '') {
                continue;
            }
            $base = [
                'scenario' => $scenario, 'trigger' => $trigger,
                'contract_flight_id' => $p->contract_flight_id, 'contract_flight_passenger_id' => $p->id,
                'recipient_type' => $r['type'], 'recipient_id' => $r['id'], 'recipient_name' => $r['name'],
                'subject' => $subject, 'body' => $body, 'sent_by' => $opts['sent_by'] ?? auth()->id(),
            ];

            if (in_array('sms', $channels, true) && $rule['via_sms']) {
                $logs[] = self::deliverSms($base, $r['phone']);
            }
            if (in_array('email', $channels, true) && $rule['via_email'] && $r['type'] === 'agent') {
                $logs[] = self::deliverEmail($base, $r['email']);
            }
            if (in_array('whatsapp', $channels, true)) {
                $logs[] = self::log($base + ['channel' => 'whatsapp', 'to' => $r['phone'], 'status' => $r['phone'] ? 'prepared' : 'skipped',
                    'error' => $r['phone'] ? null : 'No phone on file']);
            }
        }

        return $logs;
    }

    /**
     * Automatic: the pipeline moved for this passenger. Fires the scenario
     * only if its rule is switched to auto. Swallows everything.
     */
    public static function auto(string $scenario, ContractFlightPassenger $p, ?string $note = null): void
    {
        try {
            if (! self::installed() || ! isset(self::SCENARIOS[$scenario])) {
                return;
            }
            if (! self::rule($scenario)['auto']) {
                return;
            }
            self::send($scenario, $p, ['note' => $note, 'trigger' => 'auto']);
        } catch (\Throwable $e) {
            Log::warning("ContractFlightMessenger::auto({$scenario}) failed for passenger {$p->id}: ".$e->getMessage());
        }
    }

    /** Which scenario a stage key maps to, if any. */
    public static function scenarioForStage(?string $stage): ?string
    {
        foreach (self::SCENARIOS as $key => $s) {
            if (($s['stage'] ?? null) === $stage) {
                return $key;
            }
        }

        return $stage ? 'stage_update' : null;
    }

    /** Automatic, for every passenger on a flight (schedule changed, etc.). */
    public static function autoForFlight(string $scenario, ContractFlight $flight, ?string $note = null): void
    {
        try {
            if (! self::installed() || ! self::rule($scenario)['auto']) {
                return;
            }
            $flight->passengers()->with(['passportHolder', 'agent', 'ticketPurchases'])->get()
                ->each(fn (ContractFlightPassenger $p) => self::send($scenario, $p->setRelation('contractFlight', $flight), ['note' => $note, 'trigger' => 'auto']));
        } catch (\Throwable $e) {
            Log::warning("ContractFlightMessenger::autoForFlight({$scenario}) failed for flight {$flight->id}: ".$e->getMessage());
        }
    }

    // ── Channels ────────────────────────────────────────────────────────

    private static function deliverSms(array $base, ?string $phone): ContractFlightMessage
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 10) {
            return self::log($base + ['channel' => 'sms', 'to' => $phone, 'status' => 'skipped', 'error' => 'No usable phone number on file']);
        }
        try {
            $response = Http::asForm()->timeout(20)->withoutVerifying()->post(
                config('services.mram.endpoint', 'https://msg.mram.com.bd/smsapi'),
                ['api_key' => config('services.mram.api_key'), 'type' => 'text',
                    'contacts' => preg_replace('/^0/', '880', $digits), 'senderid' => config('services.mram.sender_id'), 'msg' => $base['body']]
            );
            $ok = $response->successful();

            return self::log($base + ['channel' => 'sms', 'to' => $digits, 'status' => $ok ? 'sent' : 'failed',
                'error' => $ok ? null : 'Gateway HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200)]);
        } catch (\Throwable $e) {
            return self::log($base + ['channel' => 'sms', 'to' => $digits, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private static function deliverEmail(array $base, ?string $email): ContractFlightMessage
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::log($base + ['channel' => 'email', 'to' => $email, 'status' => 'skipped', 'error' => 'No valid email address on file']);
        }
        try {
            $subject = $base['subject'];
            Mail::raw($base['body'], fn ($m) => $m->to($email, $base['recipient_name'])->subject($subject));

            return self::log($base + ['channel' => 'email', 'to' => $email, 'status' => 'sent']);
        } catch (\Throwable $e) {
            return self::log($base + ['channel' => 'email', 'to' => $email, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    private static function log(array $row): ContractFlightMessage
    {
        if (! self::installed()) {
            return new ContractFlightMessage($row);
        }

        return ContractFlightMessage::create($row);
    }
}
