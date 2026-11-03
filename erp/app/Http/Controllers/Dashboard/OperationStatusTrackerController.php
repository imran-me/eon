<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Company;
use App\Models\ContractFile;
use App\Models\ContractFlightPassenger;
use App\Models\OperationTracker;
use App\Models\TicketPurchase;
use App\Models\User;
use App\Models\VisaProcess;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\Rule;

class OperationStatusTrackerController implements HasMiddleware
{
    private const TRAVEL_COMPANY_ID = 2;

    /**
     * Accounts that are not staff. Same list Employee Task Oversight uses in
     * boardablePeople() — kept identical on purpose so the two screens agree on
     * who works here. Change one and change the other.
     */
    private const EXTERNAL_ROLES = ['agent', 'vendor', 'customer'];

    /**
     * The exception chips. Each key is both the query value and the item flag it
     * filters on, so a chip cannot say one thing and filter another. `unassigned`
     * is the one that is not a plain boolean on the item — see flagFilter().
     */
    private const FLAGS = [
        'any' => 'All',
        'attention' => 'Needs attention',
        'overdue' => 'Overdue',
        'due_soon' => 'Due in 48h',
        'blocked' => 'Blocked',
        'stale' => 'Stale',
        'unassigned' => 'Unassigned',
    ];

    /**
     * Which date the range applies to. A tracker row carries two, and filtering
     * the wrong one is silently misleading — so the choice is explicit in the
     * bar rather than assumed.
     */
    private const DATE_FIELDS = [
        'due' => ['label' => 'Deadline', 'key' => 'due_at'],
        'activity' => ['label' => 'Last update', 'key' => 'last_activity_at'],
    ];

    private const RANGES = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'Last Week',
        'month' => 'Last Month',
        'all' => 'All',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view operation tracker|view all operation tracker', only: ['index', 'print']),
            new Middleware('permission:edit operation tracker', only: ['update', 'assign', 'unassign', 'addNote']),
        ];
    }

    /**
     * Every filter step shared by the board/table view (index) and the
     * standalone print sheet (print) — everything up to the final sorted
     * list of visible items. Kept as one method so the two never drift into
     * showing different rows for what looks like the same filter bar.
     */
    private function resolveVisibleItems(Request $request): array
    {
        $request->validate([
            'view' => ['nullable', Rule::in(['attention', 'all', 'board'])],
            'type' => ['nullable', Rule::in(['all', ...OperationTracker::TYPES])],
            'assignee' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            'open' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['all', ...OperationTracker::STATUSES])],
            'priority' => ['nullable', Rule::in(['all', ...OperationTracker::PRIORITIES])],
            'flag' => ['nullable', Rule::in(array_keys(self::FLAGS))],
            'date_field' => ['nullable', Rule::in(array_keys(self::DATE_FIELDS))],
            'range' => ['nullable', Rule::in(array_keys(self::RANGES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $companyId = $this->companyId();
        $items = $this->operationItems($companyId);

        if (! Auth::user()->can('view all operation tracker')) {
            $me = (int) Auth::id();
            // Own-only users see what they own *or* were pulled into.
            $items = $items->filter(fn (array $item) => $item['assignee_id'] === $me
                || in_array($me, array_column($item['collaborators'], 'id'), true))->values();
        }

        $type = $request->input('type', 'all');
        $assignee = $request->integer('assignee') ?: null;
        $search = mb_strtolower(trim((string) $request->input('search')));
        $status = $request->input('status', 'all');
        $priority = $request->input('priority', 'all');
        $flag = $request->input('flag', 'any');
        $dateField = $request->input('date_field', 'due');
        $range = $request->input('range', 'all');

        if ($type !== 'all') {
            $items = $items->where('type', $type)->values();
        }
        if ($assignee) {
            // An assignee filter should also surface records that person was looped into.
            $items = $items->filter(fn (array $item) => $item['assignee_id'] === $assignee
                || in_array($assignee, array_column($item['collaborators'], 'id'), true))->values();
        }
        if ($search !== '') {
            $items = $items->filter(fn (array $item) => str_contains(mb_strtolower($item['search_text']), $search))->values();
        }
        // A typed range beats a preset button; presets are just shorthand for one.
        [$from, $to] = $this->resolveRange($range, $request->input('from'), $request->input('to'));
        if ($from || $to) {
            $key = self::DATE_FIELDS[$dateField]['key'];
            $items = $items->filter(function (array $item) use ($key, $from, $to) {
                $value = $item[$key] ?? null;
                if (! $value) {
                    return false;   // no date on the record cannot fall inside a range
                }
                return (! $from || $value->gte($from)) && (! $to || $value->lte($to));
            })->values();
        }

        // Counted here, before the drill-downs below, and that placement is the
        // point: the cards describe the scope you are looking at (type, assignee,
        // search, date), and the chips drill into it. Counting after the chips
        // would make every card echo the chip you just pressed — press Overdue
        // and OVERDUE becomes the total, which tells you nothing you did not
        // already know and hides the rest of the picture.
        $stats = [
            'active' => $items->whereNotIn('normalized_status', ['completed', 'rejected'])->count(),
            'attention' => $items->where('needs_attention', true)->count(),
            'overdue' => $items->where('is_overdue', true)->count(),
            'blocked' => $items->where('is_blocked', true)->count(),
            'stale' => $items->where('is_stale', true)->count(),
            'due_soon' => $items->where('is_due_soon', true)->count(),
            'unassigned' => $items->whereNull('assignee_id')->whereNotIn('normalized_status', ['completed', 'rejected'])->count(),
            'completed_today' => $items->where('completed_today', true)->count(),
        ];

        if ($status !== 'all') {
            $items = $items->where('normalized_status', $status)->values();
        }
        if ($priority !== 'all') {
            $items = $items->where('priority', $priority)->values();
        }
        if ($flag !== 'any') {
            $items = $items->filter($this->flagFilter($flag))->values();
        }

        $view = $request->input('view', 'attention');
        $board = $items->groupBy('normalized_status');
        $visibleItems = $view === 'attention'
            ? $items->where('needs_attention', true)->values()
            : $items;

        $visibleItems = $visibleItems->sortByDesc('urgency_score')->values();

        $flagOptions = self::FLAGS;
        $dateFieldOptions = array_map(fn ($d) => $d['label'], self::DATE_FIELDS);
        $rangeOptions = self::RANGES;
        $filters = [
            'type' => $type, 'assignee' => $assignee, 'search' => $request->input('search'),
            'status' => $status, 'priority' => $priority, 'flag' => $flag,
            'date_field' => $dateField, 'range' => $range,
            'from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d'),
        ];
        $filteredActive = $type !== 'all' || $assignee || $search !== '' || $status !== 'all'
            || $priority !== 'all' || $flag !== 'any' || $from || $to;

        return compact(
            'items', 'visibleItems', 'stats', 'view', 'board', 'type', 'assignee', 'search',
            'companyId', 'flagOptions', 'dateFieldOptions', 'rangeOptions', 'filters', 'filteredActive'
        );
    }

    public function index(Request $request)
    {
        [
            'items' => $items, 'visibleItems' => $visibleItems, 'stats' => $stats, 'view' => $view,
            'board' => $board, 'type' => $type, 'assignee' => $assignee, 'search' => $search,
            'companyId' => $companyId, 'flagOptions' => $flagOptions, 'dateFieldOptions' => $dateFieldOptions,
            'rangeOptions' => $rangeOptions, 'filters' => $filters, 'filteredActive' => $filtered,
        ] = $this->resolveVisibleItems($request);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $datas = new LengthAwarePaginator(
            $visibleItems->forPage($page, $perPage)->values(),
            $visibleItems->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Print becomes "open the real invoice" only when every row on the
        // page is the same Contract Flight batch — a mixed list (multiple
        // flights, or other trackable types) has no single invoice to open,
        // so it falls back to the plain table print.
        $currentPageItems = $datas->getCollection();
        $invoiceFlightId = $currentPageItems->isNotEmpty()
            && $currentPageItems->every(fn (array $item) => $item['type'] === 'contract_flight'
                && $item['contract_flight_id'] === $currentPageItems->first()['contract_flight_id'])
            ? $currentPageItems->first()['contract_flight_id']
            : null;

        // Who may be put on a record: current staff only.
        $staff = $this->currentStaff($companyId);

        // The assignee dropdown and the assignee filter also have to name anyone
        // already on a record, even if they have since left — otherwise opening
        // the drawer would show their assignment as blank and the next save
        // would silently unassign them.
        $onRecords = $items->pluck('assignee_id')
            ->merge($items->flatMap(fn (array $item) => array_column($item['collaborators'], 'id')))
            ->filter()->map(fn ($id) => (int) $id)->unique()
            ->diff($staff->pluck('id'))->values();

        $formerly = $onRecords->isEmpty() ? collect() : User::whereIn('id', $onRecords)
            ->orderBy('name')->get(['id', 'name'])
            ->each(fn (User $user) => $user->name = $user->name . ' (no longer active)');

        $assignees = $staff->concat($formerly);

        $openKey = (string) $request->input('open', '');

        return view('operation-status-tracker.index', compact(
            'datas', 'stats', 'assignees', 'staff', 'view', 'type', 'assignee', 'search',
            'board', 'openKey', 'filters', 'filtered', 'flagOptions', 'dateFieldOptions', 'rangeOptions',
            'invoiceFlightId'
        ));
    }

    /**
     * A standalone, print-only sheet — modeled on the Party Statement print
     * view (its own document, no app shell), because printing the live board
     * page produced the sidebar/header/stat cards in the output and split
     * one row per sheet. Shows every row the current filters match, not just
     * one paginated page — a printed list should be complete.
     */
    public function print(Request $request)
    {
        [
            'visibleItems' => $visibleItems, 'view' => $view, 'type' => $type, 'search' => $search,
            'flagOptions' => $flagOptions, 'dateFieldOptions' => $dateFieldOptions, 'filters' => $filters,
        ] = $this->resolveVisibleItems($request);

        $company = Company::find(Auth::user()->company_id) ?? Company::find(2) ?? Company::first();

        return view('operation-status-tracker.print', [
            'items' => $visibleItems,
            'view' => $view,
            'type' => $type,
            'search' => $search,
            'flagOptions' => $flagOptions,
            'dateFieldOptions' => $dateFieldOptions,
            'filters' => $filters,
            'company' => $company,
        ]);
    }

    public function update(Request $request, string $_role, string $type, int $id)
    {
        abort_unless(in_array($type, OperationTracker::TYPES, true), 404);

        $source = $this->findSource($type, $id);
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', $this->assignableRule($type, $id, $source)],
            'priority' => ['required', Rule::in(OperationTracker::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:1000'],
            'blocked_reason' => ['nullable', 'string', 'max:1000'],
            'update_note' => ['required', 'string', 'min:3', 'max:1500'],
        ], [
            'assigned_to.in' => 'That person is not a current employee of this company.',
        ]);

        $oldNativeStatus = $this->nativeStatusLabel($type, $source);

        DB::transaction(function () use ($validated, $type, $id, $source, $oldNativeStatus) {
            if ($type === 'visa') {
                // Officer assignment is already a native Visa field, so keep it
                // aligned with the control-tower assignee. Status/stage deliberately
                // stay read-only here: their module screens own the associated
                // business rules (including ticket accounting side effects).
                $source->update(['assigned_officer_id' => $validated['assigned_to'] ?? null]);
            }

            $normalizedStatus = $this->normalizedStatus($type, $source->fresh());
            $tracker = OperationTracker::updateOrCreate(
                ['trackable_type' => $type, 'trackable_id' => $id],
                [
                    'company_id' => $this->companyId(),
                    'normalized_status' => $normalizedStatus,
                    'assigned_to' => $validated['assigned_to'] ?? null,
                    'priority' => $validated['priority'],
                    'due_at' => $validated['due_at'] ?? null,
                    'next_action' => $validated['next_action'] ?? null,
                    'blocked_reason' => $validated['blocked_reason'] ?? null,
                    'last_activity_at' => now(),
                    'completed_at' => in_array($normalizedStatus, ['completed', 'rejected'], true) ? now() : null,
                    'created_by' => OperationTracker::where('trackable_type', $type)->where('trackable_id', $id)->value('created_by') ?: Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );

            $tracker->histories()->create([
                'event_type' => 'tracking_updated',
                'from_status' => $oldNativeStatus,
                'to_status' => $this->nativeStatusLabel($type, $source->fresh()),
                'note' => $validated['update_note'],
                'changed_by' => Auth::id(),
            ]);
        });

        return $this->backToTracker($type, $id, 'Operation status updated successfully.');
    }

    /**
     * Pull extra people onto a record. The single `assigned_to` assignee still
     * answers for it — these are people asked to look, each notified once with
     * the note that explains why.
     */
    public function assign(Request $request, string $_role, string $type, int $id)
    {
        abort_unless(in_array($type, OperationTracker::TYPES, true), 404);

        $source = $this->findSource($type, $id);
        $validated = $request->validate([
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'distinct', $this->assignableRule($type, $id, $source)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'users.*.in' => 'Only current employees of this company can be added to a record.',
        ]);

        $reference = $this->referenceFor($type, $source);
        $note = $validated['note'] ?? null;

        $attached = DB::transaction(function () use ($type, $id, $source, $validated, $note) {
            $tracker = $this->ensureTracker($type, $id, $source);

            $payload = collect($validated['users'])
                ->mapWithKeys(fn ($userId) => [(int) $userId => [
                    'note' => $note,
                    'assigned_by' => Auth::id(),
                ]])
                ->all();

            $result = $tracker->collaborators()->syncWithoutDetaching($payload);
            $attached = array_map('intval', $result['attached'] ?? []);

            if ($attached) {
                $names = User::whereIn('id', $attached)->orderBy('name')->pluck('name')->implode(', ');
                $tracker->histories()->create([
                    'event_type' => 'assignee_added',
                    'from_status' => null,
                    'to_status' => $names,
                    'note' => $note ?: 'Asked ' . $names . ' to look into this.',
                    'changed_by' => Auth::id(),
                ]);
            }

            return $attached;
        });

        if ($attached) {
            NotificationService::notifyOperationAssigned(
                $attached,
                $reference,
                $this->trackerLink($type, $id),
                $note
            );
        }

        return $this->backToTracker($type, $id, $attached
            ? count($attached) . ' person(s) added and notified.'
            : 'Those people were already on this record.');
    }

    public function unassign(Request $request, string $_role, string $type, int $id, int $assignee)
    {
        abort_unless(in_array($type, OperationTracker::TYPES, true), 404);
        $this->findSource($type, $id);

        $tracker = OperationTracker::where('trackable_type', $type)->where('trackable_id', $id)->first();
        if (! $tracker) {
            return $this->backToTracker($type, $id, 'Nothing to remove.');
        }

        DB::transaction(function () use ($tracker, $assignee) {
            $name = User::whereKey($assignee)->value('name');
            if ($tracker->collaborators()->detach($assignee)) {
                $tracker->histories()->create([
                    'event_type' => 'assignee_removed',
                    'from_status' => $name,
                    'to_status' => null,
                    'note' => ($name ?: 'A user') . ' was removed from this record.',
                    'changed_by' => Auth::id(),
                ]);
            }
        });

        return $this->backToTracker($type, $id, 'Person removed from this record.');
    }

    /**
     * Post a note. Everyone on the record — the assignee and every collaborator —
     * is notified, except whoever wrote it.
     */
    public function addNote(Request $request, string $_role, string $type, int $id)
    {
        abort_unless(in_array($type, OperationTracker::TYPES, true), 404);

        $source = $this->findSource($type, $id);
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $reference = $this->referenceFor($type, $source);

        $audience = DB::transaction(function () use ($type, $id, $source, $validated) {
            $tracker = $this->ensureTracker($type, $id, $source);

            $tracker->histories()->create([
                'event_type' => OperationTracker::NOTE_EVENT,
                'from_status' => null,
                'to_status' => null,
                'note' => $validated['body'],
                'changed_by' => Auth::id(),
            ]);

            return $tracker->load('collaborators:id')->audienceIds();
        });

        if ($audience) {
            NotificationService::notifyOperationNote(
                $audience,
                $reference,
                $this->trackerLink($type, $id),
                $validated['body']
            );
        }

        return $this->backToTracker($type, $id, 'Note posted'
            . ($audience ? ' — ' . count($audience) . ' person(s) notified.' : '. Nobody is assigned yet, so no one was notified.'));
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function operationItems(int $companyId): Collection
    {
        $trackers = OperationTracker::query()
            ->with(['assignee:id,name', 'collaborators:id,name', 'histories.changedBy:id,name'])
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(fn (OperationTracker $tracker) => "{$tracker->trackable_type}:{$tracker->trackable_id}");

        $items = collect();

        if ($companyId === self::TRAVEL_COMPANY_ID) {
            VisaProcess::with(['passportHolder:id,name,passport_no', 'country:id,name', 'visaCategory:id,name', 'assignedOfficer:id,name'])
                ->get()->each(function (VisaProcess $visa) use ($items, $trackers) {
                    $tracker = $trackers->get("visa:{$visa->id}");
                    $assignee = $tracker ? $tracker->assignee : $visa->assignedOfficer;
                    $items->push($this->makeItem(
                        type: 'visa',
                        source: $visa,
                        tracker: $tracker,
                        reference: $this->referenceFor('visa', $visa),
                        client: $visa->passportHolder?->name ?: 'Unknown applicant',
                        context: collect([$visa->country?->name, $visa->visaCategory?->name])->filter()->implode(' · '),
                        stage: $visa->stage ?: ucfirst((string) $visa->status),
                        assignee: $assignee,
                        nativeDue: $visa->travel_date,
                        sourceUrl: $this->deepLink('visa', $visa->id),
                        statusOptions: $this->allowedStatuses('visa'),
                        sourceStage: $visa->stage,
                    ));
                });

            ContractFile::with(['passportHolder:id,name,passport_no', 'country:id,name', 'category:id,name'])
                ->get()->each(function (ContractFile $file) use ($items, $trackers) {
                    $tracker = $trackers->get("file:{$file->id}");
                    $received = $file->documents_received;
                    $total = $file->documents_total;
                    $stage = $file->status_label . ($total ? " · {$received}/{$total} docs" : '');
                    $items->push($this->makeItem(
                        type: 'file',
                        source: $file,
                        tracker: $tracker,
                        reference: $this->referenceFor('file', $file),
                        client: $file->applicant_name ?: 'Unknown applicant',
                        context: collect([$file->country?->name, $file->category?->name])->filter()->implode(' · '),
                        stage: $stage,
                        assignee: $tracker?->assignee,
                        nativeDue: $file->expected_out_date,
                        sourceUrl: $this->deepLink('file', $file->id, $this->referenceFor('file', $file)),
                        statusOptions: $this->allowedStatuses('file'),
                    ));
                });

            ContractFlightPassenger::with([
                    'contractFlight.flightCategory:id,name',
                    'passportHolder:id,name,passport_no,phone',
                    'agent:id,name',
                    'ticketPurchases:id,ticket_no,status',
                ])
                // Reads the passenger's own handling type, not the flight's:
                // one agent's batch routinely mixes boarding-only travellers
                // with ones taking full immigration support, and the flight
                // now only carries the default the cards started from.
                ->where('handling_type', 'manpower_wise')
                ->get()->each(function (ContractFlightPassenger $passenger) use ($items, $trackers) {
                    $tracker = $trackers->get("contract_flight:{$passenger->id}");
                    $flight = $passenger->contractFlight;
                    $pnrs = $passenger->ticketPurchases->pluck('ticket_no')->filter()->implode(', ');
                    $stageLabel = ContractFlightPassenger::stageLabel($passenger->stage);
                    if ($passenger->exception_flag) {
                        $stageLabel .= ' — ⚠ ' . (ContractFlightPassenger::EXCEPTION_FLAGS[$passenger->exception_flag] ?? $passenger->exception_flag);
                    }
                    $items->push($this->makeItem(
                        type: 'contract_flight',
                        source: $passenger,
                        tracker: $tracker,
                        reference: $this->referenceFor('contract_flight', $passenger),
                        client: $passenger->passportHolder?->name ?: 'Unknown passenger',
                        context: collect([$flight?->route, $flight?->flightCategory?->name, $pnrs ?: 'No PNR linked'])->filter()->implode(' · '),
                        stage: $stageLabel,
                        assignee: $tracker?->assignee,
                        nativeDue: $flight?->departure_at,
                        sourceUrl: $this->deepLink('contract_flight', $passenger->id),
                        statusOptions: $this->allowedStatuses('contract_flight'),
                        contractFlightId: $flight?->id,
                    ));
                });
        }

        TicketPurchase::with(['passportHolder:id,name,passport_no', 'ticket.from_airport:id,code', 'ticket.to_airport:id,code', 'legs', 'creator:id,name', 'updater:id,name'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
                if ($companyId === self::TRAVEL_COMPANY_ID) {
                    $query->orWhereNull('company_id');
                }
            })
            ->get()->each(function (TicketPurchase $ticket) use ($items, $trackers) {
                $tracker = $trackers->get("ticket:{$ticket->id}");
                $route = collect([$ticket->ticket?->from_airport?->code, $ticket->ticket?->to_airport?->code])->filter()->implode(' → ');
                $nativeDue = $ticket->legs->min('travel_date');
                $assignee = $tracker ? $tracker->assignee : ($ticket->updater ?: $ticket->creator);
                $items->push($this->makeItem(
                    type: 'ticket',
                    source: $ticket,
                    tracker: $tracker,
                    reference: $this->referenceFor('ticket', $ticket),
                    client: $ticket->passportHolder?->name ?: 'Walk-in customer',
                    context: collect([$route, ucfirst((string) $ticket->trip_type)])->filter()->implode(' · '),
                    stage: $this->allowedStatuses('ticket')[$ticket->status] ?? ucfirst((string) $ticket->status),
                    assignee: $assignee,
                    nativeDue: $nativeDue ? Carbon::parse($nativeDue) : null,
                    sourceUrl: $this->deepLink('ticket', $ticket->id),
                    statusOptions: $this->allowedStatuses('ticket'),
                ));
            });

        return $items;
    }

    private function makeItem(
        string $type,
        $source,
        ?OperationTracker $tracker,
        string $reference,
        string $client,
        string $context,
        string $stage,
        $assignee,
        $nativeDue,
        string $sourceUrl,
        array $statusOptions,
        ?string $sourceStage = null,
        ?int $contractFlightId = null,
    ): array {
        $normalizedStatus = $this->normalizedStatus($type, $source);
        $terminal = in_array($normalizedStatus, ['completed', 'rejected'], true);
        $nativeDueAt = $nativeDue ? Carbon::parse($nativeDue) : null;
        if ($nativeDueAt && $nativeDueAt->format('H:i:s') === '00:00:00') {
            $nativeDueAt->endOfDay();
        }
        $dueAt = $tracker?->due_at ?: $nativeDueAt;

        // Freshness is whichever moved last. Reading only the tracker would
        // freeze the row the moment anyone saved here — work done afterwards in
        // the source module would never register, and every lazily-created
        // tracker row (a note, an assignment) would start that clock.
        $lastActivity = collect([$tracker?->last_activity_at, $source->updated_at])->filter()->max();

        $histories = $tracker?->histories ?? collect();
        $notes = $histories->where('event_type', OperationTracker::NOTE_EVENT)->values();
        $collaborators = ($tracker?->collaborators ?? collect())
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'note' => $user->pivot->note,
            ])->values()->all();

        $isBlocked = filled($tracker?->blocked_reason) && ! $terminal;
        $isOverdue = $dueAt && $dueAt->isPast() && ! $terminal;
        $isStale = $lastActivity && $lastActivity->lt(now()->subDays(3)) && ! $terminal;
        $isDueSoon = $dueAt && $dueAt->between(now(), now()->addHours(48)) && ! $terminal;
        $assigneeId = $assignee?->id;
        $needsAttention = $isBlocked || $isOverdue || $isStale || (! $assigneeId && ! $terminal);
        $urgency = ($isOverdue ? 1000 + abs(now()->diffInHours($dueAt, false)) : 0)
            + ($isBlocked ? 500 : 0) + (! $assigneeId ? 300 : 0) + ($isStale ? 100 : 0)
            + match ($tracker?->priority ?? 'normal') { 'urgent' => 80, 'high' => 40, 'low' => -5, default => 0 };

        return [
            'type' => $type,
            'id' => $source->id,
            'reference' => $reference,
            'client' => $client,
            'context' => $context,
            'stage' => $stage,
            'source_status' => (string) $source->status,
            'source_stage' => $sourceStage,
            'status_options' => $statusOptions,
            'normalized_status' => $normalizedStatus,
            'assignee_id' => $assigneeId,
            'assignee_name' => $assignee?->name,
            'collaborators' => $collaborators,
            'priority' => $tracker?->priority ?? 'normal',
            'due_at' => $dueAt,
            'next_action' => $tracker?->next_action,
            'blocked_reason' => $tracker?->blocked_reason,
            'last_activity_at' => $lastActivity,
            'histories' => $histories->take(8),
            'notes' => $notes,
            'latest_note' => $notes->first()?->note,
            'note_count' => $notes->count(),
            'is_blocked' => $isBlocked,
            'is_overdue' => $isOverdue,
            'is_stale' => $isStale,
            'is_due_soon' => $isDueSoon,
            'needs_attention' => $needsAttention,
            'completed_today' => $terminal && $source->updated_at?->isToday(),
            'urgency_score' => $urgency,
            'source_url' => $sourceUrl,
            'contract_flight_id' => $contractFlightId,
            'search_text' => implode(' ', [
                $type, $reference, $client, $context, $stage, $assignee?->name,
                implode(' ', array_column($collaborators, 'name')),
            ]),
        ];
    }

    /**
     * The test behind one exception chip. Every flag but `unassigned` is already
     * a boolean on the item; unassigned means "nobody owns it AND it is still
     * live", which is the same reading the UNASSIGNED card uses.
     */
    private function flagFilter(string $flag): callable
    {
        if ($flag === 'unassigned') {
            return fn (array $item) => ! $item['assignee_id']
                && ! in_array($item['normalized_status'], ['completed', 'rejected'], true);
        }

        $key = match ($flag) {
            'attention' => 'needs_attention',
            'overdue' => 'is_overdue',
            'due_soon' => 'is_due_soon',
            'blocked' => 'is_blocked',
            'stale' => 'is_stale',
        };

        return fn (array $item) => (bool) $item[$key];
    }

    /**
     * Turn a preset button, or a typed pair of dates, into one [from, to] span.
     * A typed date always wins over a preset — the preset is only shorthand.
     * "Last week"/"Last month" are rolling windows back from now, not calendar
     * weeks, so the answer means the same thing whatever day you ask on.
     */
    private function resolveRange(string $range, $from, $to): array
    {
        if (filled($from) || filled($to)) {
            return [
                filled($from) ? Carbon::parse($from)->startOfDay() : null,
                filled($to) ? Carbon::parse($to)->endOfDay() : null,
            ];
        }

        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'month' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            default => [null, null],
        };
    }

    /** The label this record is known by on its own module screen. */
    private function referenceFor(string $type, $source): string
    {
        return match ($type) {
            'visa' => $source->application_id ?: 'VSA-' . str_pad((string) $source->id, 4, '0', STR_PAD_LEFT),
            'file' => (string) $source->file_number,
            'ticket' => $source->ticket_no ?: 'TKT-' . str_pad((string) $source->id, 4, '0', STR_PAD_LEFT),
            'contract_flight' => ($source->contractFlight?->flight_number ?: 'CF') . '-' . str_pad((string) $source->id, 3, '0', STR_PAD_LEFT),
        };
    }

    /**
     * A link that lands on the record itself, not on a list to hunt through.
     *
     * Tickets have a real show page. Visas and files do not, so their index
     * takes `?open={id}` and pops the detail view the screen already has.
     *
     * The contract-file link also carries `?search=` because that screen
     * paginates 25 at a time and hands its modal only the current page — the
     * search narrows the list so the record is certain to be on it. The visa
     * board renders every card, so `open` alone is enough there.
     *
     * Every route is existence-checked: a half-deployed module must degrade to
     * the plain index, never throw while the tracker is rendering.
     */
    private function deepLink(string $type, int $id, ?string $reference = null): string
    {
        $role = request()->route('role');

        [$name, $params] = match ($type) {
            'visa' => ['role.visa.index', ['open' => $id]],
            'file' => ['role.contract-files.index', ['open' => $id, 'search' => $reference]],
            'ticket' => ['role.ticket-purchase.show', ['ticket_purchase' => $id]],
            'contract_flight' => ['role.contract-flights.index', ['search' => ContractFlightPassenger::find($id)?->contractFlight?->flight_number]],
        };

        if (RouteFacade::has($name)) {
            return route($name, array_merge(['role' => $role], $params));
        }

        $fallback = match ($type) {
            'visa' => 'role.visa.index',
            'file' => 'role.contract-files.index',
            'ticket' => 'role.ticket-purchase.index',
            'contract_flight' => 'role.contract-flights.index',
        };

        return RouteFacade::has($fallback) ? route($fallback, ['role' => $role]) : '#';
    }

    /** Back to this tracker with the record's drawer already open. */
    private function trackerLink(string $type, int $id): string
    {
        return route('role.operation-status-tracker.index', [
            'role' => request()->route('role'),
            'view' => 'all',
            'open' => "{$type}:{$id}",
        ]);
    }

    private function backToTracker(string $type, int $id, string $message)
    {
        return redirect()
            ->to($this->trackerLink($type, $id))
            ->with('success', $message);
    }

    /**
     * A tracker row is created lazily — the first time somebody assigns a
     * person or posts a note against a record that has never been touched here.
     */
    private function ensureTracker(string $type, int $id, $source): OperationTracker
    {
        return OperationTracker::firstOrCreate(
            ['trackable_type' => $type, 'trackable_id' => $id],
            [
                'company_id' => $this->companyId(),
                'normalized_status' => $this->normalizedStatus($type, $source),
                'priority' => 'normal',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );
    }

    /**
     * Current employees of this company — the only people this screen offers to
     * put on a record. Excludes the agent/vendor/customer accounts that share
     * the users table, and anyone whose status is no longer active (resigned,
     * inactive). Soft-deleted users are already out via the model's global scope.
     */
    private function currentStaff(int $companyId): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($role) => $role->whereNotIn('name', self::EXTERNAL_ROLES))
            ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Valid people for a save against one record: current staff, plus whoever
     * is already on that record. The second half matters — re-saving a record
     * owned by someone who has since resigned must not be rejected.
     */
    private function assignableRule(string $type, int $id, $source)
    {
        $ids = $this->currentStaff($this->companyId())->pluck('id')->all();

        $tracker = OperationTracker::where('trackable_type', $type)->where('trackable_id', $id)->first();
        if ($tracker) {
            $ids[] = $tracker->assigned_to;
            $ids = array_merge($ids, $tracker->collaborators()->pluck('users.id')->all());
        }
        if ($type === 'visa') {
            $ids[] = $source->assigned_officer_id;
        }

        return Rule::in(array_values(array_unique(array_filter(array_map('intval', $ids)))));
    }

    private function normalizedStatus(string $type, $source): string
    {
        if ($type === 'visa') {
            if ($source->status === 'rejected') return 'rejected';
            if ($source->status === 'approved') return ($source->stage === 'Ready for Delivery') ? 'ready' : 'completed';
            if ($source->stage === 'Document Collection') return 'new';
            if (in_array($source->stage, ['Final Approval Wait'], true)) return 'waiting';
            return 'in_progress';
        }

        if ($type === 'file') {
            return match ($source->status) {
                'doc_collection' => 'new',
                'submitted_to_vendor', 'under_process' => 'in_progress',
                'approved' => 'ready',
                'delivered' => 'completed',
                'rejected' => 'rejected',
                default => 'in_progress',
            };
        }

        if ($type === 'contract_flight') {
            if (in_array($source->exception_flag, ['cancelled', 'flight_cancelled', 'deported', 'deported_we_bear'], true)) {
                return 'rejected';
            }
            return match ($source->stage) {
                'passenger_received', 'information_entered', 'documents_received' => 'new',
                'documents_verified', 'service_defined', 'route_confirmed', 'ticket_issued',
                'ready_for_travel', 'confirmation_call', 'preflight_coordination', 'airport_processing',
                'immigration', 'boarding', 'departed', 'transit', 'landed' => 'in_progress',
                'arrival_confirmed' => 'waiting',
                'case_closed' => 'completed',
                default => 'in_progress',
            };
        }

        return match ($source->status) {
            'draft' => 'new',
            'pending' => 'waiting',
            'confirm' => 'completed',
            'cancelled' => 'rejected',
            default => 'in_progress',
        };
    }

    private function allowedStatuses(string $type): array
    {
        return match ($type) {
            'visa' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
            'file' => ContractFile::STATUSES,
            'ticket' => ['draft' => 'Draft', 'pending' => 'Pending', 'confirm' => 'Confirmed', 'cancelled' => 'Cancelled'],
            'contract_flight' => ContractFlightPassenger::stages(),
        };
    }

    private function nativeStatusLabel(string $type, $source): string
    {
        $key = $type === 'contract_flight' ? $source->stage : $source->status;
        $label = $this->allowedStatuses($type)[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
        return $type === 'visa' && $source->stage ? "{$label} · {$source->stage}" : $label;
    }

    private function findSource(string $type, int $id)
    {
        if (in_array($type, ['visa', 'file', 'contract_flight'], true) && $this->companyId() !== self::TRAVEL_COMPANY_ID) {
            abort(404);
        }

        return match ($type) {
            'visa' => VisaProcess::findOrFail($id),
            'file' => ContractFile::findOrFail($id),
            'contract_flight' => ContractFlightPassenger::where('handling_type', 'manpower_wise')->findOrFail($id),
            'ticket' => TicketPurchase::where(function ($query) {
                $query->where('company_id', $this->companyId());
                if ($this->companyId() === self::TRAVEL_COMPANY_ID) {
                    $query->orWhereNull('company_id');
                }
            })->findOrFail($id),
        };
    }

    private function companyId(): int
    {
        return (int) (Auth::user()->company_id ?: self::TRAVEL_COMPANY_ID);
    }
}
