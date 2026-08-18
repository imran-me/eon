<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Notifications\SupportTicketClosed;
use App\Notifications\SupportTicketCreated;
use App\Notifications\SupportTicketReplied;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminSupportTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $user = auth()->user();

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

        // Employee can only see tickets assigned to them
        // $query->where('assigned_to', $user->id);

        $datas = $query->orderBy('title', 'asc')->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Support tickets retrieved successfully.',
            'data' => $datas
        ]);
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
            'file_attachment'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            // customer_id can be passed if needed
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $data = SupportTicket::create([
            'title' => $request->title,
            'description' => $request->description,
            'ticket_department_id' => $request->ticket_department_id,
            'priority' => $request->priority,
            'status' => $request->status ?? 'open',
            'assigned_to' => $request->assigned_to ?? $user->id, // Default to self if not assigned
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'customer_id' => $request->customer_id,
        ]);

        if ($request->hasFile('file_attachment')) {
            $file = $request->file('file_attachment');
            $file_name = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
            $upload_path = 'image/support_tickets/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }
            $file->move(public_path($upload_path), $file_name);
            $data->file_attachment = $upload_path . $file_name;
            $data->save();
        }

        // Notify super admin ALWAYS - immediate
        $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
        if ($superAdmin && $superAdmin->id !== $user->id) {
            $superAdmin->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(1)));
        }

        if ($data->assigned_to && $data->assigned_to !== $user->id) {
            $data->assignedTo->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(3)));
        }

        if ($data->customer_id && $data->customer) {
            $data->customer->notify((new SupportTicketCreated($data))->delay(now()->addSeconds(5)));
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => $data
        ], 201);
    }

    /**
     * Display the specified resource, including replies (chat).
     */
    public function show($id)
    {
        $ticket = SupportTicket::with([
            'replies.user', 
            'ticketDepartment', 
            'assignedTo', 
            'createdBy', 
            'company', 
            'customer'
        ])->findOrFail($id);

        // Security check for employee: must be assigned to this ticket or created by them
        $user = auth()->user();
        if ($ticket->assigned_to !== $user->id && $ticket->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this ticket.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket details retrieved successfully.',
            'data' => $ticket
        ]);
    }

    /**
     * Store a reply to the ticket (Chat).
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = SupportTicket::findOrFail($id);
        $user = Auth::user();

        // dd($ticket->assigned_to, $ticket->created_by, $user->id);

        // Check permission
        if ($ticket->assigned_to !== $user->id && $ticket->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reply to this ticket.'
            ], 403);
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $id,
            'user_id' => $user->id,
            'content' => $request->content,
            'replied_by' => $user->id,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $file_name = uniqid() . '.' . strtolower($file->getClientOriginalExtension());
            $upload_path = 'image/support_tickets/';
            if (!file_exists(public_path($upload_path))) {
                mkdir(public_path($upload_path), 0777, true);
            }
            $file->move(public_path($upload_path), $file_name);
            $reply->attachment_path = $upload_path . $file_name;
            $reply->save();
        }

        // Notify super admin
        $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
        if ($superAdmin && $superAdmin->id !== $user->id) {
            $superAdmin->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(1)));
        }

        if ($ticket->created_by && $ticket->created_by !== $user->id) {
            $ticket->createdBy->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(3)));
        }

        if ($ticket->assigned_to && $ticket->assigned_to !== $user->id) {
            $ticket->assignedTo->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(5)));
        }

        if ($ticket->customer_id && $ticket->customer) {
            $ticket->customer->notify((new SupportTicketReplied($ticket, $reply))->delay(now()->addSeconds(7)));
        }

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully.',
            'data' => $reply->load('user')
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = SupportTicket::findOrFail($id);
        $user = Auth::user();

        // Check permission
        if ($data->assigned_to !== $user->id && $data->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this ticket.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'                 => 'sometimes|required|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'ticket_department_id'  => 'sometimes|required|integer|exists:ticket_departments,id',
            'priority'              => 'sometimes|required|string|in:low,medium,high',
            'status'                => 'sometimes|required|string|in:open,in_progress,resolved,closed',
            'file_attachment'       => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldStatus = $data->status;

            $data->update($request->only([
                'title', 'description', 'ticket_department_id', 'priority', 'status', 'assigned_to', 'customer_id'
            ]));
            
            $data->updated_by = $user->id;
            $data->save();

            if ($oldStatus !== 'closed' && $request->status === 'closed') {
                $superAdmin = \App\Models\User::role(['super admin', 'Super Admin', 'admin'])->first();
                if ($superAdmin && $superAdmin->id !== $user->id) {
                    $superAdmin->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(1)));
                }

                if ($data->createdBy && $data->created_by !== $user->id) {
                    $data->createdBy->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(3)));
                }

                if ($data->assigned_to && $data->assigned_to !== $user->id) {
                    $data->assignedTo->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(5)));
                }

                if ($data->customer_id && $data->customer) {
                    $data->customer->notify((new SupportTicketClosed($data))->delay(now()->addSeconds(7)));
                }
            }

            if ($request->hasFile('file_attachment')) {
                $file_attachment = $request->file('file_attachment');
                $file_name = uniqid() . '.' . strtolower($file_attachment->getClientOriginalExtension());
                $upload_path = 'image/support_tickets/';

                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

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
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $data = SupportTicket::find($id);
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket Not Found!'
                ], 404);
            }
            
            $user = Auth::user();
            if ($data->assigned_to !== $user->id && $data->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this ticket.'
                ], 403);
            }

            $data->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
