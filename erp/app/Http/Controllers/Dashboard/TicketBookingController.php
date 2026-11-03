<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\SendTicketBookingNotification;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\Portal;
use App\Models\Ticket;
use App\Models\TicketBooking;
use App\Models\TicketPurchase;
use App\Models\User;
use App\Support\TravelCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view ticket purchase|view all ticket purchase')->only(['index', 'show']);
        $this->middleware('permission:create ticket purchase')->only(['create', 'store']);
        $this->middleware('permission:edit ticket purchase')->only(['edit', 'update', 'changeStatus', 'resend']);
        $this->middleware('permission:delete ticket purchase')->only(['destroy']);
    }

    private function companyId(): int
    {
        return TravelCompany::ID;
    }

    private function scopedQuery()
    {
        return TicketBooking::query()->when($this->companyId(), fn ($q, $id) => $q->where('company_id', $id));
    }

    public function index(Request $request)
    {
        $base = $this->scopedQuery();
        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'booked')->where('expires_at', '>', now())->count(),
            'expiring' => (clone $base)->where('status', 'booked')->whereBetween('expires_at', [now(), now()->addHours(3)])->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
        ];
        $bookings = $base->with(['passenger', 'ticket', 'recipient', 'vendor', 'portal'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('ticket_id'), fn ($q) => $q->where('ticket_id', $request->ticket_id))
            // The bar's From / To: the day the hold was entered.
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->to_date))
            // Any text the table shows (owner, 2026-10-06): every word must match
            // the reference, PNR, status, amount, passenger, route, seller or who
            // is notified; "EP-VN-139" / "139" finds that vendor.
            ->when($request->filled('search'), function ($q) use ($request) {
                foreach (preg_split('/[[:space:]]+/', trim((string) $request->search)) as $w) {
                    if ($w === '') {
                        continue;
                    }
                    $like = '%'.$w.'%';
                    $q->where(function ($x) use ($w, $like) {
                        $x->where('booking_reference', 'like', $like)->orWhere('pnr', 'like', $like)->orWhere('status', 'like', $like)
                            ->orWhereHas('passenger', fn ($r) => $r->where('name', 'like', $like)->orWhere('passport_no', 'like', $like))
                            ->orWhereHas('ticket', fn ($r) => $r->where('title', 'like', $like))
                            ->orWhereHas('vendor', fn ($r) => $r->where('name', 'like', $like))
                            ->orWhereHas('portal', fn ($r) => $r->where('name', 'like', $like))
                            ->orWhereHas('recipient', fn ($r) => $r->where('name', 'like', $like));
                        $id = preg_replace('/^EP-(VN|AG|CU)-0*/i', '', $w);
                        if ($id !== '' && ctype_digit($id)) {
                            $x->orWhere('vendor_id', (int) $id);
                        }
                        $amount = str_replace(',', '', $w);
                        if (is_numeric($amount)) {
                            $x->orWhere('quoted_amount', 'like', $amount.'%');
                        }
                    });
                }
            })
            ->latest()->paginate(15)->withQueryString();
        $tickets = Ticket::where('status', 1)->orderBy('title')->get();

        return view('dashboard.ticket_bookings.index', compact('bookings', 'stats', 'tickets'));
    }

    public function create()
    {
        return view('dashboard.ticket_bookings.form', $this->formData(new TicketBooking([
            'booking_at' => now(), 'expires_at' => now()->addHours(6), 'currency' => 'BDT',
            'status' => 'booked', 'notify_in_app' => true, 'notify_email' => true, 'notify_sms' => true,
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['company_id'] = $this->companyId();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['booking_reference'] = $this->nextReference();
        $data = $this->checkboxes($request, $data);
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('ticket_booking_attachments', 'public');
        }
        $booking = TicketBooking::create($data);
        SendTicketBookingNotification::dispatch($booking->id, 'created');

        return redirect()->route('role.ticket-purchase.index', ['role' => $request->route('role'), 'company' => $booking->company_id])
            ->with('success', 'Ticket booking created and notifications queued.');
    }

    public function show(string $role, int $ticket_booking)
    {
        $booking = $this->scopedQuery()->with(['passenger', 'ticket', 'vendor', 'portal', 'recipient', 'notificationLogs', 'confirmedPurchase'])->findOrFail($ticket_booking);

        return view('dashboard.ticket_bookings.show', compact('booking'));
    }

    public function edit(string $role, int $ticket_booking)
    {
        return view('dashboard.ticket_bookings.form', $this->formData($this->scopedQuery()->findOrFail($ticket_booking)));
    }

    public function update(Request $request, string $role, int $ticket_booking)
    {
        $booking = $this->scopedQuery()->findOrFail($ticket_booking);
        $data = $this->checkboxes($request, $this->validated($request));
        $data['updated_by'] = auth()->id();
        if ($request->hasFile('attachment')) {
            if ($booking->attachment) {
                Storage::disk('public')->delete($booking->attachment);
            }
            $data['attachment'] = $request->file('attachment')->store('ticket_booking_attachments', 'public');
        }
        // A custom extension re-arms reminders that are now outside their windows.
        if (! empty($data['expires_at']) && (! $booking->expires_at || $booking->expires_at->ne($data['expires_at']))) {
            $data += ['reminded_3h_at' => null, 'reminded_1h_at' => null, 'expired_notified_at' => null];
            if ($booking->status === 'expired' && now()->lt($data['expires_at'])) {
                $data['status'] = 'booked';
            }
            // a draft / pending ticket that is given a deadline becomes a hold
            if (in_array($booking->status, ['draft', 'pending'], true)) {
                $data['status'] = 'booked';
                $data['booking_at'] = $data['booking_at'] ?? now();
            }
        }
        $booking->update($data);

        return redirect()->route('role.ticket-bookings.show', compact('role', 'ticket_booking'))->with('success', 'Booking updated.');
    }

    public function changeStatus(Request $request, string $role, int $ticket_booking)
    {
        $request->validate(['status' => ['required', Rule::in(['draft', 'pending', 'booked', 'confirmed', 'cancelled'])]]);
        $booking = $this->scopedQuery()->findOrFail($ticket_booking);
        $status = $request->status;
        // a hold needs its deadline before the reminders can run on it
        if ($status === 'booked' && ! $booking->expires_at) {
            return redirect()->route('role.ticket-bookings.edit', ['role' => $role, 'ticket_booking' => $booking->id])
                ->with('error', 'Set the hold expiry date & time first, then the ticket becomes Booked.');
        }
        if ($status === 'confirmed') {
            if (! in_array($booking->status, ['booked', 'expired', 'draft', 'pending'], true)) {
                return back()->with('error', 'Only a booked or expired booking can be converted to a purchase.');
            }
            if ($booking->confirmed_purchase_id) {
                return back()->with('success', 'This booking has already been converted to a purchase.');
            }

            return redirect()->route('role.ticket-direct-sale.create', ['role' => $role, 'booking_id' => $booking->id]);
        }
        $booking->update([
            'status' => $status, 'updated_by' => auth()->id(),
            'booking_at' => $status === 'booked' ? ($booking->booking_at ?? now()) : $booking->booking_at,
            'confirmed_at' => null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
        ]);
        if (in_array($status, ['booked', 'cancelled'], true) && $booking->notification_recipient_id) {
            SendTicketBookingNotification::dispatch($booking->id, $status);
        }

        return back()->with('success', 'Booking status changed to '.ucfirst($status).'.');
    }

    public function resend(Request $request, string $role, int $ticket_booking)
    {
        $booking = $this->scopedQuery()->findOrFail($ticket_booking);
        $event = 'manual_'.now()->format('YmdHis');
        SendTicketBookingNotification::dispatch($booking->id, $event);

        return back()->with('success', 'Notification resend queued.');
    }

    public function destroy(string $role, int $ticket_booking)
    {
        $booking = $this->scopedQuery()->findOrFail($ticket_booking);
        if ($booking->attachment) {
            Storage::disk('public')->delete($booking->attachment);
        }
        $booking->delete();

        return redirect()->route('role.ticket-bookings.index', compact('role'))->with('success', 'Booking deleted.');
    }

    private function validated(Request $request): array
    {
        $request->validate(['name_ticket_no' => 'nullable|string|max:100']);

        return TicketPurchase::nameTicketNoAttributes(
            'ticket_bookings', $request->name_ticket_no, $request->has('name_ticket_no')
        ) + $request->validate([
            // a draft / pending ticket may not know its route or deadline yet (owner, 2026-09-21)
            'passport_holder_id' => 'required|exists:passport_holders,id', 'ticket_id' => 'nullable|exists:tickets,id',
            'vendor_id' => 'nullable|exists:users,id|prohibits:portal_id',
            'portal_id' => 'nullable|exists:portals,id|prohibits:vendor_id',
            'notification_recipient_id' => 'required|exists:users,id', 'pnr' => 'nullable|string|max:100',
            'trip_type' => 'required|in:one-way,two-way,multi-city', 'ticket_class' => 'required|in:economy,business,first',
            'booking_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:booking_at|after:now',
            'departure_at' => 'nullable|date', 'return_at' => 'nullable|date|after_or_equal:departure_at',
            'quoted_amount' => 'nullable|numeric|min:0', 'currency' => 'required|string|max:5',
            'notes' => 'nullable|string|max:5000', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
    }

    private function checkboxes(Request $request, array $data): array
    {
        foreach (['notify_in_app', 'notify_email', 'notify_sms'] as $key) {
            $data[$key] = $request->boolean($key);
        }

        return $data;
    }

    private function formData(TicketBooking $booking): array
    {
        $companyId = $this->companyId();
        $recipients = User::where('status', 'active')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->orderBy('name')->get();
        $boss = User::where('name', 'Md Mohsin')->where('status', 'active')->first();
        if ($boss && ! $recipients->contains('id', $boss->id)) {
            $recipients->push($boss);
        }

        return [
            'booking' => $booking, 'passengers' => PassportHolder::where('status', 1)->orderBy('name')->get(),
            'phCategories' => PassportHolderCategory::orderBy('name')->get(),
            'tickets' => Ticket::where('status', 1)->orderBy('title')->get(),
            'vendors' => User::role('vendor')->orderBy('name')->get(), 'portals' => Portal::where('status', 'active')->orderBy('name')->get(),
            'recipients' => $recipients->sortBy('name'), 'defaultRecipientId' => $booking->notification_recipient_id ?: $boss?->id,
        ];
    }

    private function nextReference(): string
    {
        return DB::transaction(function () {
            $last = TicketBooking::withTrashed()->lockForUpdate()->latest('id')->value('id') ?? 0;

            return 'TB'.str_pad($last + 1, 7, '0', STR_PAD_LEFT);
        });
    }
}
