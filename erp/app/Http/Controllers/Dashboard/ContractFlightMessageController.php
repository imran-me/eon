<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ContractFlight;
use App\Models\ContractFlightMessage;
use App\Models\ContractFlightMessageRule;
use App\Models\ContractFlightPassenger;
use App\Services\ContractFlightMessenger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Messages — the Contract Flight communication desk. Compose a notice to
 * passengers and/or their agents (by flight, by stage, by agent, or a
 * hand-picked few), preview exactly what each person will get, send by SMS
 * / email / WhatsApp; set which scenarios go out automatically when the
 * pipeline moves; read the log of everything sent. All sending is
 * ContractFlightMessenger's; this controller only chooses recipients.
 */
class ContractFlightMessageController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract flight|view all contract flight', only: ['index', 'recipients', 'preview']),
            new Middleware('permission:edit contract flight', only: ['send', 'saveRules']),
        ];
    }

    public function index(Request $request, string $role)
    {
        $installed = ContractFlightMessenger::installed();

        $flights = ContractFlight::with(['ticket.airline', 'ticket.from_airport', 'ticket.to_airport'])
            ->withCount('passengers')
            ->orderByRaw("FIELD(status, 'open', 'boarding', 'departed', 'cancelled')")
            ->orderByDesc('departure_at')
            ->limit(200)->get();

        $agents = ContractFlightPassenger::query()
            ->whereNotNull('agent_id')->with('agent:id,name,phone,email')
            ->get(['agent_id'])->pluck('agent')->filter()->unique('id')->sortBy('name')->values();

        $log = $installed
            ? ContractFlightMessage::with(['sender:id,name', 'flight:id,flight_number'])
                ->when($request->filled('log_scenario'), fn ($q) => $q->where('scenario', $request->log_scenario))
                ->when($request->filled('log_channel'), fn ($q) => $q->where('channel', $request->log_channel))
                ->when($request->filled('log_status'), fn ($q) => $q->where('status', $request->log_status))
                ->when($request->filled('log_q'), fn ($q) => $q->where(fn ($w) => $w->where('recipient_name', 'like', "%{$request->log_q}%")->orWhere('to', 'like', "%{$request->log_q}%")->orWhere('body', 'like', "%{$request->log_q}%")))
                ->orderByDesc('id')->paginate(50, ['*'], 'log_page')->withQueryString()
            : null;

        $stats = $installed ? [
            'today' => ContractFlightMessage::whereDate('created_at', today())->where('status', 'sent')->count(),
            'week' => ContractFlightMessage::where('created_at', '>=', now()->startOfWeek())->where('status', 'sent')->count(),
            'failed' => ContractFlightMessage::where('created_at', '>=', now()->subDays(7))->where('status', 'failed')->count(),
            'auto' => ContractFlightMessage::where('created_at', '>=', now()->subDays(7))->where('trigger', 'auto')->count(),
        ] : ['today' => 0, 'week' => 0, 'failed' => 0, 'auto' => 0];

        return view('contract-flights.messages', [
            'role' => $role,
            'installed' => $installed,
            'rules' => ContractFlightMessenger::rules(),
            'scenarios' => ContractFlightMessenger::SCENARIOS,
            'placeholders' => ContractFlightMessenger::PLACEHOLDERS,
            'stages' => ContractFlightPassenger::stages(),
            'flights' => $flights,
            'agents' => $agents,
            'log' => $log,
            'stats' => $stats,
        ]);
    }

    /**
     * The people a filter selects — one row per passenger with their
     * contact and their agent's — for the compose step's checklist.
     */
    public function recipients(Request $request, string $role)
    {
        // With a scenario, each row also carries the message that person
        // (and their agent) would receive, fully filled in — amounts, times,
        // PNRs — so the page can show and let staff edit the real text.
        $scenario = isset(ContractFlightMessenger::SCENARIOS[$request->scenario]) ? $request->scenario : null;
        $rule = $scenario ? ContractFlightMessenger::rule($scenario) : null;
        $pTpl = $request->filled('passenger_template') ? $request->passenger_template : ($rule['passenger_template'] ?? '');
        $aTpl = $request->filled('agent_template') ? $request->agent_template : ($rule['agent_template'] ?? '');

        $q = ContractFlightPassenger::query()
            ->with(['passportHolder:id,name,passport_no,phone', 'agent:id,name,phone,email', 'contractFlight.ticket.airline', 'contractFlight.ticket.from_airport', 'contractFlight.ticket.to_airport', 'ticketPurchases:id,ticket_no,status'])
            ->when($request->filled('flight_id') && $request->flight_id !== 'active',
                fn ($q) => $q->where('contract_flight_id', $request->flight_id))
            ->when($request->flight_id === 'active' || ! $request->filled('flight_id'),
                fn ($q) => $q->whereHas('contractFlight', fn ($f) => $f->whereIn('status', ['open', 'boarding'])))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->stage))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->agent_id))
            ->limit(500);

        $rows = $q->get()->map(function (ContractFlightPassenger $p) use ($scenario, $pTpl, $aTpl, $request) {
            $f = $p->contractFlight;
            $ctx = $scenario ? ContractFlightMessenger::context($p, $request->note) : null;
            return [
                'passenger_message' => $ctx ? ContractFlightMessenger::render($pTpl, $ctx) : null,
                'agent_message' => $ctx ? ContractFlightMessenger::render($aTpl, $ctx) : null,
                'id' => $p->id,
                'name' => $p->passportHolder?->name ?? 'Passenger',
                'passport' => $p->passportHolder?->passport_no,
                'phone' => $p->phone,
                'flight' => $f?->flight_number,
                'flight_id' => $p->contract_flight_id,
                'route' => ($f?->ticket?->from_airport?->code ?? '—') . ' → ' . ($f?->ticket?->to_airport?->code ?? '—'),
                'departure' => $f?->departure_at?->format('d M, h:i A'),
                'stage' => ContractFlightPassenger::stageLabel($p->stage),
                'exception' => $p->exception_flag ? (ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag) : null,
                'pnr' => $p->ticketPurchases->where('status', '!=', 'cancelled')->pluck('ticket_no')->filter()->implode(', '),
                'agent_id' => $p->agent_id,
                'agent' => $p->agent?->name,
                'agent_phone' => $p->agent?->phone,
                'agent_email' => $p->agent?->email,
            ];
        });

        return response()->json(['success' => true, 'passengers' => $rows]);
    }

    /** What one passenger (and their agent) would receive — rendered, not sent. */
    public function preview(Request $request, string $role)
    {
        $data = $request->validate([
            'scenario' => ['required', Rule::in(array_keys(ContractFlightMessenger::SCENARIOS))],
            'passenger_id' => ['required', 'exists:contract_flight_passengers,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'passenger_template' => ['nullable', 'string', 'max:2000'],
            'agent_template' => ['nullable', 'string', 'max:2000'],
        ]);
        $p = ContractFlightPassenger::findOrFail($data['passenger_id']);
        $rule = ContractFlightMessenger::rule($data['scenario']);
        $ctx = ContractFlightMessenger::context($p, $data['note'] ?? null);
        return response()->json([
            'success' => true,
            'passenger' => ContractFlightMessenger::render($data['passenger_template'] ?? $rule['passenger_template'], $ctx),
            'agent' => ContractFlightMessenger::render($data['agent_template'] ?? $rule['agent_template'], $ctx),
            'passenger_phone' => $p->phone, 'agent_phone' => $p->agent?->phone, 'agent_email' => $p->agent?->email, 'agent_name' => $p->agent?->name,
        ]);
    }

    public function send(Request $request, string $role)
    {
        if (! ContractFlightMessenger::installed()) {
            return response()->json(['success' => false, 'message' => 'Messaging is not installed yet (migration pending).'], 409);
        }
        $data = $request->validate([
            'scenario' => ['required', Rule::in(array_keys(ContractFlightMessenger::SCENARIOS))],
            'passenger_ids' => ['required', 'array', 'min:1', 'max:500'],
            'passenger_ids.*' => ['integer', 'exists:contract_flight_passengers,id'],
            'to' => ['required', 'array', 'min:1'], 'to.*' => [Rule::in(['passenger', 'agent'])],
            'channels' => ['required', 'array', 'min:1'], 'channels.*' => [Rule::in(['sms', 'email', 'whatsapp'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:150'],
            'passenger_template' => ['nullable', 'string', 'max:2000'],
            'agent_template' => ['nullable', 'string', 'max:2000'],
            // Text edited for one person on the page wins over the template.
            'overrides' => ['nullable', 'array'],
            'overrides.*.passenger' => ['nullable', 'string', 'max:2000'],
            'overrides.*.agent' => ['nullable', 'string', 'max:2000'],
        ]);
        $overrides = $data['overrides'] ?? [];

        $passengers = ContractFlightPassenger::with(['passportHolder', 'agent', 'contractFlight', 'ticketPurchases'])->whereIn('id', $data['passenger_ids'])->get();
        $logs = collect();
        // One agent may stand behind many passengers on a batch; when the
        // notice is not about one passenger in particular (schedule, delay,
        // custom) the agent hears once per flight, not once per head.
        $perPassenger = in_array($data['scenario'], ['payment_reminder', 'ticket_issued', 'documents_received', 'documents_verified', 'stage_update', 'passenger_received', 'ready_for_travel', 'boarding', 'departed', 'landed', 'arrival_confirmed', 'offloaded'], true);
        $agentsTold = [];

        foreach ($passengers as $p) {
            foreach ($data['to'] as $who) {
                if ($who === 'agent' && ! $perPassenger) {
                    $key = $p->agent_id . ':' . $p->contract_flight_id;
                    if (! $p->agent_id || isset($agentsTold[$key])) continue;
                    $agentsTold[$key] = true;
                }
                $logs = $logs->merge(ContractFlightMessenger::send($data['scenario'], $p, [
                    'note' => $data['note'] ?? null, 'trigger' => 'manual', 'channels' => $data['channels'], 'only' => $who,
                    'subject' => $data['subject'] ?? null,
                    'template' => $overrides[$p->id][$who] ?? ($who === 'passenger' ? ($data['passenger_template'] ?? null) : ($data['agent_template'] ?? null)),
                    'sent_by' => auth()->id(),
                ]));
            }
        }

        $counts = $logs->countBy('status');
        // WhatsApp links for the page to open — one per prepared row.
        $whatsapp = $logs->where('channel', 'whatsapp')->where('status', 'prepared')->map(fn ($l) => [
            'name' => $l->recipient_name,
            'url' => 'https://wa.me/' . preg_replace('/^0/', '880', preg_replace('/\D/', '', (string) $l->to)) . '?text=' . rawurlencode($l->body),
        ])->values();

        return response()->json([
            'success' => ($counts['sent'] ?? 0) + ($counts['prepared'] ?? 0) > 0,
            'sent' => $counts['sent'] ?? 0, 'failed' => $counts['failed'] ?? 0, 'skipped' => $counts['skipped'] ?? 0, 'prepared' => $counts['prepared'] ?? 0,
            'whatsapp' => $whatsapp,
            'message' => sprintf('%d sent · %d failed · %d skipped (no contact)%s', $counts['sent'] ?? 0, $counts['failed'] ?? 0, $counts['skipped'] ?? 0,
                ($counts['prepared'] ?? 0) ? ' · ' . $counts['prepared'] . ' WhatsApp ready' : ''),
        ]);
    }

    public function saveRules(Request $request, string $role)
    {
        if (! ContractFlightMessenger::installed()) {
            return response()->json(['success' => false, 'message' => 'Messaging is not installed yet (migration pending).'], 409);
        }
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.scenario' => ['required', Rule::in(array_keys(ContractFlightMessenger::SCENARIOS))],
            'rules.*.auto' => ['nullable', 'boolean'], 'rules.*.to_passenger' => ['nullable', 'boolean'], 'rules.*.to_agent' => ['nullable', 'boolean'],
            'rules.*.via_sms' => ['nullable', 'boolean'], 'rules.*.via_email' => ['nullable', 'boolean'],
            'rules.*.passenger_template' => ['nullable', 'string', 'max:2000'], 'rules.*.agent_template' => ['nullable', 'string', 'max:2000'],
        ]);
        foreach ($data['rules'] as $r) {
            ContractFlightMessageRule::updateOrCreate(['scenario' => $r['scenario']], [
                'auto' => (bool) ($r['auto'] ?? false), 'to_passenger' => (bool) ($r['to_passenger'] ?? false), 'to_agent' => (bool) ($r['to_agent'] ?? false),
                'via_sms' => (bool) ($r['via_sms'] ?? false), 'via_email' => (bool) ($r['via_email'] ?? false),
                'passenger_template' => $r['passenger_template'] ?? '', 'agent_template' => $r['agent_template'] ?? '',
            ]);
        }
        return response()->json(['success' => true, 'rules' => ContractFlightMessenger::rules()]);
    }
}
