<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\TicketDepartment;
use App\Models\User;
use App\Notifications\SupportTicketClosed;
use App\Notifications\SupportTicketCreated;
use App\Notifications\SupportTicketReplied;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupportTicketController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view support ticket', only: ['index', 'show']),
            new Middleware('permission:create support ticket', only: ['store', 'storeReply']),
            new Middleware('permission:edit support ticket', only: ['update', 'updateReply']),
            new Middleware('permission:delete support ticket', only: ['destroy', 'destroyReply']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with('ticketDepartment', 'assignedTo', 'createdBy', 'company', 'customer');

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        if ($request->filled('ticket_department_id')) {
            $query->where('ticket_department_id', $request->ticket_department_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $user = auth()->user();

        /*
|--------------------------------------------------------------------------
| Role Based Ticket Visibility
|--------------------------------------------------------------------------
*/

        if ($user->hasRole('customer')) {
            $query->where('customer_id', $user->id);
        }

        if ($user->hasRole('employee')) {
            $query->where('assigned_to', $user->id);
        }

        $datas = $query->orderBy('title', 'asc')->paginate(30);


        $ticketDepartments = TicketDepartment::orderBy('name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();
        $companies = Company::orderBy('name', 'asc')->get();
        $employees = User::role('employee')->orderBy('name', 'asc')->get();

        return view('support-tickets.index', compact(
            'datas',
            'customers',
            'ticketDepartments',
            'companies',
            'employees'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'                 => 'required|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'ticket_department_id'  => 'required|integer|exists:ticket_departments,id',
            'priority'              => 'required|string|in:low,medium,high',
            'status'                => 'nullable|string|in:open,in_progress,resolved,closed',
            'company_id'            => 'nullable|integer|exists:companies,id',
            'file_attachment'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
        if ('employee' == Auth::user()->getRoleNames()->first()) {
            $request->merge(['company_id' => Auth::user()->company_id]);
        }
        // Create
        $data = SupportTicket::updateOrCreate([
            'title' => $request->title
        ], [
            'description' => $request->description,
            'ticket_department_id' => $request->ticket_department_id,
            'priority' => $request->priority,
            'status' => $request->status ?? 'open',
            'assigned_to' => $request->assigned_to,
            'created_by' => Auth::id(),
            'company_id' => $request->company_id,
            'customer_id' => $request->customer_id,
        ]);

        // 2. Handle File Attachment Upload
        if ($request->hasFile('file_attachment')) {
            $file = $request->file('file_attachment');
            $file_name = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
            $upload_path = 'image/support_tickets/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }
            $success = $file->move(public_path($upload_path), $file_name);
            if ($success) {
                if (!empty($data->file_attachment) && file_exists(public_path($data->file_attachment))) {
                    unlink(public_path($data->file_attachment));
                }
                $data->file_attachment = $upload_path . $file_name;
                $data->save();
            }
        }

        // Notify super admin ALWAYS - immediate
        $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
        if ($superAdmin && $superAdmin->id !== Auth::id()) {
            $superAdmin->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(1)));
        }

        // Notify assigned employee ALWAYS (even if they created it) - delayed by 3 seconds
        if ($data->assigned_to) {
            $data->assignedTo->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(3)));
        }

        // Notify customer ALWAYS - delayed by 5 seconds
        if ($data->customer_id && $data->customer) {
            $data->customer->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(5)));
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully and notifications sent.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.support-tickets.index')->with('success', 'Data created successfully and notifications sent.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = SupportTicket::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'title'                 => 'required|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'ticket_department_id'  => 'required|integer|exists:ticket_departments,id',
            'priority'              => 'required|string|in:low,medium,high',
            'status'                => 'required|string|in:open,in_progress,resolved,closed',
            'company_id'            => 'required|integer|exists:companies,id',
            'file_attachment'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            // Store old status to check if it changed
            $oldStatus = $data->status;

            $data->update([
                'title' => $request->title,
                'description' => $request->description,
                'ticket_department_id' => $request->ticket_department_id,
                'priority' => $request->priority,
                'status' => $request->status,
                'assigned_to' => $request->assigned_to,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'company_id' => $request->company_id,
                'customer_id' => $request->customer_id,
            ]);

            // Notify when ticket is closed
            if ($oldStatus !== 'closed' && $request->status === 'closed') {
                // Notify super admin - immediate
                $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
                if ($superAdmin && $superAdmin->id !== Auth::id()) {
                    $superAdmin->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(1)));
                }

                // Notify ticket creator - delayed by 3 seconds
                if ($data->createdBy && $data->created_by !== Auth::id()) {
                    $data->createdBy->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(3)));
                }

                // Notify assigned employee - delayed by 5 seconds
                if ($data->assigned_to && $data->assigned_to !== Auth::id()) {
                    $data->assignedTo->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(5)));
                }

                // Notify customer - delayed by 7 seconds
                if ($data->customer_id && $data->customer) {
                    $data->customer->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(7)));
                }
            }

            // 2. Handle File Attachment Update
            if ($request->hasFile('file_attachment')) {
                $file_attachment = $request->file('file_attachment');
                $file_name = uniqid() . '.' . strtolower($file_attachment->getClientOriginalExtension());
                $upload_path = 'image/support_tickets/';

                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Remove old file attachment if it exists
                if (!empty($data->file_attachment) && file_exists(public_path($data->file_attachment))) {
                    unlink(public_path($data->file_attachment));
                }

                $file_attachment->move(public_path($upload_path), $file_name);
                $data->file_attachment = $upload_path . $file_name;
                $data->save();
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        try {
            $data = SupportTicket::find($request->item_id);
            if ($data) {
                $data->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    /**
     * Show ticket details with replies
     */
    public function show($role, $id)
    {
        $ticket = SupportTicket::with(['replies.user', 'ticketDepartment', 'assignedTo', 'createdBy', 'company', 'customer'])
            ->findOrFail($id);

        return view('support-tickets.show', compact('ticket'));
    }

    /**
     * Store a reply
     */
    public function storeReply(Request $request, $role, $ticketId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = SupportTicket::findOrFail($ticketId);

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticketId,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'replied_by' => Auth::id(),
        ]);

        // 2. Handle File Attachment Upload
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $file_name = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
            $upload_path = 'image/support_tickets/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }
            $success = $file->move(public_path($upload_path), $file_name);
            if ($success) {
                if (!empty($reply->attachment_path) && file_exists(public_path($reply->attachment_path))) {
                    unlink(public_path($reply->attachment_path));
                }
                $reply->attachment_path = $upload_path . $file_name;
                $reply->save();
            }
        }

        // Notify super admin ALWAYS - immediate
        $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
        if ($superAdmin && $superAdmin->id !== Auth::id()) {
            $superAdmin->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(1)));
        }

        // Notify ticket creator (if they didn't reply) - delayed by 3 seconds
        if ($ticket->created_by && $ticket->created_by !== Auth::id()) {
            $ticket->createdBy->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(3)));
        }

        // Notify assigned employee (if they didn't reply) - delayed by 5 seconds
        if ($ticket->assigned_to && $ticket->assigned_to !== Auth::id()) {
            $ticket->assignedTo->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(5)));
        }

        // Notify customer ALWAYS - delayed by 7 seconds
        if ($ticket->customer_id && $ticket->customer) {
            $ticket->customer->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(7)));
        }

        // Update ticket status
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully.',
            'reply' => $reply->load('user')
        ]);
    }

    /**
     * Update a reply
     */
    public function updateReply(Request $request, $role, $replyId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $reply = SupportTicketReply::findOrFail($replyId);

        if ($reply->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $reply->update(['content' => $request->content]);

        return response()->json(['success' => true, 'message' => 'Reply updated successfully.', 'reply' => $reply]);
    }

    /**
     * Delete a reply
     */
    public function destroyReply($role, $replyId)
    {
        $reply = SupportTicketReply::findOrFail($replyId);

        if ($reply->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($reply->attachment_path) {
            Storage::disk('public')->delete($reply->attachment_path);
        }

        $reply->delete();

        return response()->json(['success' => true, 'message' => 'Reply deleted successfully.']);
    }

    public function downloadAttachment($role, $filename)
    {
        $reply = SupportTicketReply::where('attachment_path', 'like', "%{$filename}")->firstOrFail();
        $fullPath = public_path($reply->attachment_path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        $mimeType = mime_content_type($fullPath);
        $fileName = basename($reply->attachment_path);

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }
}
