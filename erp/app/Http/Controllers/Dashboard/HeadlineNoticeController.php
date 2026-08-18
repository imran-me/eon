<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContractFlight;
use App\Models\LeadFollowup;
use App\Models\LeadReminder;
use App\Models\Leave;
use App\Models\Notice;
use App\Models\Notification;
use App\Services\DmApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HeadlineNoticeController extends Controller
{
    /** The ticker polls every 30s on every page, so DM is only hit this often. */
    private const DM_CACHE_TTL = 300;

    /** Keeps the Renewal Center from crowding out the rest of the bulletin. */
    private const DM_MAX_ITEMS = 12;

    /** The Renewal Center is a super-admin surface, so its bulletin rows are too. */
    private const DM_ROLE = 'super admin';

    /** Personal alerts lead the bulletin, so they are capped tighter than the rest. */
    private const ALERT_MAX_ITEMS = 10;

    public function index(Request $request, string $role): JsonResponse
    {
        $user = $request->user();
        $items = collect();
        $departmentId = $user->profile?->department_id;

        // The signed-in user's own notifications, mirrored into the bulletin until
        // they either read them in the bell or clear them from the ticker. Unlike
        // every other row here, an alert is dismissed by the reader rather than
        // resolved by the work behind it.
        $alerts = Notification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', Notification::DISPLAY_TYPES)
            ->onBulletin()
            ->latest()
            ->limit(self::ALERT_MAX_ITEMS)
            ->get()
            ->map(function (Notification $notification) use ($user, $role) {
                $message = (string) ($notification->data['message'] ?? 'You have a new notification');

                $item = $this->item(
                    'notification-'.$notification->id,
                    'alert',
                    $this->alertLabel($notification->type),
                    $this->alertIcon($notification->type),
                    // A long message would stretch the scroll loop; the tooltip keeps it whole.
                    Str::limit($message, 70),
                    $notification->created_at,
                    'critical',
                    $this->alertUrl($notification, $user, $role),
                    '',
                    $notification->created_at->diffForHumans(short: true)
                );

                $item['tooltip'] = $message;

                // The ticker POSTs here when the row is clicked. It only clears the
                // bulletin copy - the notification itself stays unread in the bell.
                $item['dismiss_url'] = route('role.notifications.dismiss-bulletin', [
                    'role' => $role,
                    'id' => $notification->id,
                ]);
                unset($item['sort_at']);

                return $item;
            });

        // Published notices are audience content, so every signed-in user may see them.
        $notices = Notice::query()
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('publish_date')->orWhereDate('publish_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->when($user->company_id, fn ($query, $companyId) => $query->where(
                fn ($audience) => $audience->whereNull('company_id')->orWhere('company_id', $companyId)
            ))
            ->when($departmentId, fn ($query, $id) => $query->where(
                fn ($audience) => $audience->whereNull('department_id')->orWhere('department_id', $id)
            ))
            ->latest('publish_date')
            ->limit(8)
            ->get();

        foreach ($notices as $notice) {
            $date = $notice->expiry_date ? Carbon::parse($notice->expiry_date)->endOfDay() : null;
            $items->push($this->item(
                'notice-'.$notice->id,
                'notice',
                'Notice',
                $notice->icon ?: '📢',
                $notice->title,
                $date,
                'normal',
                $this->can($user, 'view users')
                    ? route('role.notices.index', ['role' => $role, 'title' => $notice->title, 'headline' => 'notice-'.$notice->id])
                    : route('role.dashboard', ['role' => $role]),
                '#notice-'.$notice->id
            ));
        }

        if ($this->can($user, 'view lead reminder')) {
            LeadReminder::with('lead:id,name')
                ->where('status', 'pending')
                ->where('due_date', '<=', now()->addDays(7))
                ->orderBy('due_date')
                ->limit(10)
                ->get()
                ->each(function (LeadReminder $reminder) use ($items, $role) {
                    $date = Carbon::parse($reminder->due_date);
                    $lead = $reminder->lead?->name ?: 'Lead #'.$reminder->lead_id;
                    $items->push($this->item(
                        'lead-reminder-'.$reminder->id,
                        'reminder',
                        'Reminder',
                        '🔔',
                        $reminder->title.' - '.$lead,
                        $date,
                        $date->isPast() ? 'critical' : 'high',
                        route('role.lead-reminders.index', ['role' => $role, 'lead_id' => $reminder->lead_id, 'status' => 'pending', 'headline' => 'lead-reminder-'.$reminder->id]),
                        '#lead-reminder-'.$reminder->id
                    ));
                });
        }

        if ($this->can($user, 'view lead followup')) {
            LeadFollowup::with('lead:id,name')
                ->where('status', 'pending')
                ->whereNotNull('followup_date')
                ->where('followup_date', '<=', now()->addDays(7))
                ->orderBy('followup_date')
                ->limit(10)
                ->get()
                ->each(function (LeadFollowup $followup) use ($items, $role) {
                    $date = Carbon::parse($followup->followup_date);
                    $lead = $followup->lead?->name ?: 'Lead #'.$followup->lead_id;
                    $items->push($this->item(
                        'lead-followup-'.$followup->id,
                        'followup',
                        'Follow-up',
                        '☎',
                        ucfirst($followup->type).' with '.$lead,
                        $date,
                        $date->isPast() ? 'critical' : 'high',
                        route('role.lead-followup.index', ['role' => $role, 'lead_id' => $followup->lead_id, 'status' => 'pending', 'headline' => 'lead-followup-'.$followup->id]),
                        '#lead-followup-'.$followup->id
                    ));
                });
        }

        // In this module, editing a leave is the action that approves or rejects it.
        if ($this->can($user, 'edit leave')) {
            Leave::with(['user:id,name', 'leave_type:id,name'])
                ->where('status', 'pending')
                ->oldest('created_at')
                ->limit(10)
                ->get()
                ->each(function (Leave $leave) use ($items, $role) {
                    $employee = $leave->user?->name ?: 'Employee #'.$leave->user_id;
                    $leaveType = $leave->leave_type?->name ?: 'Leave';
                    $items->push($this->item(
                        'leave-approval-'.$leave->id,
                        'approval',
                        'Leave approval',
                        '✓',
                        $employee.' - '.$leaveType.' request needs approval',
                        Carbon::parse($leave->start_date),
                        'high',
                        route('role.leaves.index', ['role' => $role, 'user_id' => $leave->user_id, 'status' => 'pending', 'headline' => 'leave-approval-'.$leave->id]),
                        '#leave-approval-'.$leave->id
                    ));
                });
        }

        if ($this->canAny($user, ['view contract flight', 'view all contract flight'])) {
            ContractFlight::with(['ticket.airline', 'ticket.from_airport', 'ticket.to_airport'])
                ->whereIn('status', ['open', 'boarding'])
                ->whereNotNull('departure_at')
                ->whereBetween('departure_at', [now()->subDay(), now()->addDays(7)])
                ->orderBy('departure_at')
                ->limit(10)
                ->get()
                ->each(function (ContractFlight $flight) use ($items, $role) {
                    $date = $flight->departure_at;
                    $flightName = trim(($flight->airline?->name ? $flight->airline->name.' ' : '').($flight->airline_flight_no ?: $flight->flight_number));
                    $route = $flight->route ? ' - '.$flight->route : '';
                    $items->push($this->item(
                        'contract-flight-'.$flight->id,
                        'deadline',
                        'Flight deadline',
                        '✈',
                        $flightName.$route.' departs '.$date->format('d M, h:i A'),
                        $date,
                        $date->isPast() || $date->diffInHours(now()) <= 24 ? 'critical' : 'high',
                        route('role.contract-flights.index', ['role' => $role, 'search' => $flight->flight_number, 'headline' => 'contract-flight-'.$flight->id]),
                        '#contract-flight-'.$flight->id
                    ));
                });
        }

        // Mirrors the Renewal Center's "All" list, super admins only.
        foreach ($user->hasRole(self::DM_ROLE) ? $this->renewalRows() : [] as $row) {
            $due = Carbon::parse($row['due'])->startOfDay();
            $daysLeft = (int) round(Carbon::today()->diffInDays($due, false));

            $items->push($this->item(
                $row['id'],
                'renewal',
                $row['label'],
                $row['icon'],
                Str::limit($row['title'], 42),
                $due,
                $daysLeft < 0 ? 'critical' : ($daysLeft <= 7 ? 'high' : 'normal'),
                // Land on the dashboard and let the ticker highlight the exact card.
                route('role.dashboard', array_filter(['role' => $role, 'headline' => $row['ref']])),
                $row['ref'] ? '[data-headline-ref="'.$row['ref'].'"]' : '.rn-panel',
                ($daysLeft < 0 ? 'Overdue ' : 'Due ').$due->format('d M')
            ));
        }

        $priority = ['critical' => 0, 'high' => 1, 'normal' => 2];
        $items = $items->sortBy(fn (array $item) => [
            $priority[$item['priority']] ?? 3,
            $item['sort_at'] ?? '9999-12-31 23:59:59',
        ])->map(function (array $item) {
            unset($item['sort_at']);

            return $item;
        });

        // Alerts stay newest-first and lead the track: they are the only rows the
        // user can clear, so burying them behind the shared queue would hide them.
        $items = $alerts->concat($items)->take(35)->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Badge text for a personal alert - short enough to fit the ticker chip.
     */
    private function alertLabel(string $type): string
    {
        return match ($type) {
            'task_assigned', 'task_created' => 'New task',
            'state_changed' => 'Task moved',
            'priority_changed' => 'Priority',
            'due_date_changed' => 'Due date',
            'comment_added' => 'Comment',
            'attachment_uploaded' => 'Attachment',
            'project_status_changed' => 'Project',
            'office_todo_assigned' => 'New todo',
            'office_todo_updated', 'office_todo_status_changed' => 'Todo',
            'notice_published', 'notice_updated' => 'Notice',
            'payslip_generated' => 'Payslip',
            default => 'Alert',
        };
    }

    private function alertIcon(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'office_todo') => '📋',
            str_starts_with($type, 'notice') => '📢',
            $type === 'comment_added' => '💬',
            $type === 'attachment_uploaded' => '📎',
            $type === 'payslip_generated' => '💰',
            $type === 'project_status_changed' => '📁',
            default => '🔔',
        };
    }

    /**
     * Where clicking an alert lands. Mirrors the bell dropdown's targets, and
     * falls back to the notifications page when the payload carries no subject.
     */
    private function alertUrl(Notification $notification, $user, string $role): string
    {
        $data = $notification->data ?? [];

        if (! empty($data['task_id']) && ! empty($data['board_id'])) {
            return route('role.tasks.board', array_filter([
                'role' => $role,
                'board' => $data['board_id'],
                'task' => $data['task_id'],
                'tab' => $notification->type === 'comment_added' ? 'activity' : null,
            ]));
        }

        if (! empty($data['todo_id'])) {
            return route('role.office-todos.index', array_filter([
                'role' => $role,
                'title' => $data['todo_title'] ?? null,
            ]));
        }

        if (! empty($data['notice_id']) && $this->can($user, 'view notice')) {
            return route('role.notices.index', array_filter([
                'role' => $role,
                'title' => $data['notice_title'] ?? null,
            ]));
        }

        return route('role.notifications.index', ['role' => $role]);
    }

    private function item(
        string $id,
        string $type,
        string $label,
        string $icon,
        string $text,
        ?Carbon $date,
        string $priority,
        string $targetUrl,
        string $targetSelector,
        ?string $dueOverride = null
    ): array {
        // Notices and alerts are dated by when they were raised, not by a deadline,
        // so an old one is not a late one.
        $overdue = $date && $date->isPast() && ! $date->isToday()
            && ! in_array($type, ['notice', 'alert'], true);

        return [
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'icon' => $icon,
            'text' => $text,
            'due' => $dueOverride ?: $this->dueLabel($date, $type === 'notice'),
            'priority' => $priority,
            'overdue' => $overdue,
            'target_url' => $targetUrl,
            'target_selector' => $targetSelector,
            'sort_at' => $date?->format('Y-m-d H:i:s'),
        ];
    }

    private function dueLabel(?Carbon $date, bool $isExpiry = false): string
    {
        if (! $date) {
            return $isExpiry ? 'Active' : 'Pending';
        }

        if ($isExpiry) {
            return 'Until '.$date->format('d M');
        }

        if ($date->isToday()) {
            return trim('Today '.($date->format('H:i') === '00:00' ? '' : $date->format('h:i A')));
        }
        if ($date->isTomorrow()) {
            return 'Tomorrow';
        }
        if ($date->isPast()) {
            return 'Overdue '.(int) floor($date->diffInDays(now())).'d';
        }

        return $date->diffInDays(now()) <= 7
            ? 'In '.max(1, (int) ceil(now()->diffInDays($date))).' days'
            : $date->format('d M');
    }

    /**
     * The Renewal Center's rows, flattened to title / kind / due date and
     * cached, since every open page re-polls this endpoint twice a minute.
     *
     * @return array<int, array{id: string, label: string, icon: string, title: string, due: string}>
     */
    private function renewalRows(): array
    {
        return Cache::remember('headline-ticker.dm-renewals', self::DM_CACHE_TTL, function () {
            try {
                $dm = app(DmApiService::class);
                $window = [
                    'from' => Carbon::today()->startOfMonth()->toDateString(),
                    'to' => Carbon::today()->endOfMonth()->toDateString(),
                ];

                $accesses = $dm->fetchAccessItems(1, 200, $window);
                $documents = $dm->fetchExpiredDocuments(1, 200, $window + ['scope' => 'all']);

                // The ticker is an alert stream, so a renewal already paid for
                // has no business in it — and the Renewal Center it mirrors drops
                // the same rows, so the two cannot disagree. withoutSettled()
                // never throws; a missing table leaves the list as it was.
                return $this->collectRenewalRows(
                    \App\Models\DmRenewalPayment::withoutSettled(is_array($accesses) ? $accesses : [], \App\Models\DmRenewalPayment::SUBSCRIPTION),
                    \App\Models\DmRenewalPayment::withoutSettled(is_array($documents) ? $documents : [], \App\Models\DmRenewalPayment::DOCUMENT)
                );
            } catch (\Throwable $e) {
                logger()->warning('Headline ticker could not load DM renewals: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Same window as the Renewal Center's "All" list — subscriptions and
     * document renewals falling due inside the current month, lapsed ones
     * included. The bulletin is that list, so the two never disagree.
     *
     * @param  array  $accesses
     * @param  array  $documents
     * @return array
     */
    private function collectRenewalRows(array $accesses, array $documents): array
    {
        $from = Carbon::today()->startOfMonth();
        $to = Carbon::today()->endOfMonth();
        $rows = [];

        foreach ($accesses as $access) {
            $due = $this->dmDate(data_get($access, 'expired_date') ?: data_get($access, 'renewal_date'));

            if (! $due || $due->lt($from) || $due->gt($to)) {
                continue;
            }

            $title = data_get($access, 'name') ?: 'Untitled subscription';
            $rows[] = [
                'id' => 'dm-subscription-'.(data_get($access, 'id') ?: substr(md5($title.$due->toDateString()), 0, 8)),
                // Matches the data-headline-ref the "All" pane stamps on its rows.
                'ref' => data_get($access, 'id') ? 'dm-subscription-'.data_get($access, 'id') : '',
                'label' => 'Subscription',
                'icon' => '🔔',
                'title' => $title,
                'due' => $due->toDateString(),
            ];
        }

        foreach ($documents as $document) {
            $due = $this->dmDate(
                data_get($document, 'renewal_date')
                    ?: data_get($document, 'expired_date')
                    ?: data_get($document, 'documents.renewal_date')
            );

            if (! $due || $due->lt($from) || $due->gt($to)) {
                continue;
            }

            $title = data_get($document, 'documents.title') ?: data_get($document, 'title') ?: 'Untitled document';
            $rows[] = [
                'id' => 'dm-document-'.(data_get($document, 'id') ?: substr(md5($title.$due->toDateString()), 0, 8)),
                'ref' => data_get($document, 'id') ? 'dm-document-'.data_get($document, 'id') : '',
                'label' => 'Doc renewal',
                'icon' => '📄',
                'title' => $title,
                'due' => $due->toDateString(),
            ];
        }

        // Most overdue first, then the rest of the month by nearest due date.
        usort($rows, fn (array $a, array $b) => $a['due'] <=> $b['due']);

        return array_slice($rows, 0, self::DM_MAX_ITEMS);
    }

    /**
     * Parse a DM date payload without letting one bad value break the ticker.
     */
    private function dmDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function can($user, string $permission): bool
    {
        return $user->can($permission);
    }

    private function canAny($user, array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission) => $this->can($user, $permission));
    }
}
