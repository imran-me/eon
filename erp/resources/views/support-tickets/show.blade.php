@extends('layout.app')

@section('meta-information')
    <title>Ticket Details - {{ $ticket->title }}</title>
@endsection

@section('css')
<style>
    .ticket-header {
        background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px 8px 0 0;
    }
    .reply-item {
        border-left: 3px solid #e5e7eb;
        transition: all 0.3s;
    }
    .reply-item:hover {
        border-left-color: #3b82f6;
        background-color: #f9fafb;
    }
</style>
@endsection

@section('main-content')
<div class="container mx-auto px-4 py-6">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('role.support-tickets.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" 
           class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Back to Tickets
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold mb-2">{{ $ticket->title }}</h1>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <span><i class="fas fa-ticket-alt mr-1"></i> #{{ $ticket->id }}</span>
                        <span><i class="fas fa-building mr-1"></i> {{ $ticket->company->name }}</span>
                        <span><i class="fas fa-user mr-1"></i> {{ $ticket->customer->name }}</span>
                        <span><i class="fas fa-calendar mr-1"></i> {{ $ticket->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="px-3 py-1 rounded text-xs font-semibold
                        {{ $ticket->priority === 'low' ? 'bg-gray-200 text-gray-800' : '' }}
                        {{ $ticket->priority === 'medium' ? 'bg-blue-200 text-blue-800' : '' }}
                        {{ $ticket->priority === 'high' ? 'bg-red-200 text-red-800' : '' }}">
                        {{ strtoupper($ticket->priority) }}
                    </span>
                    <span class="px-3 py-1 rounded text-xs font-semibold
                        {{ $ticket->status === 'open' ? 'bg-yellow-200 text-yellow-800' : '' }}
                        {{ $ticket->status === 'in_progress' ? 'bg-blue-200 text-blue-800' : '' }}
                        {{ $ticket->status === 'resolved' ? 'bg-green-200 text-green-800' : '' }}
                        {{ $ticket->status === 'closed' ? 'bg-gray-200 text-gray-800' : '' }}">
                        {{ strtoupper(str_replace('_', ' ', $ticket->status)) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Ticket Details -->
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold mb-3">Description</h3>
            <p class="text-gray-700 whitespace-pre-line">{{ $ticket->description }}</p>

            <!-- Add File Attachment Here -->
            @if($ticket->file_attachment)
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-paperclip mr-1"></i> Ticket Attachment
                    </h4>
                    @php
                        $extension = strtolower(pathinfo($ticket->file_attachment, PATHINFO_EXTENSION));
                        $iconClass = 'fas fa-file';
                        $iconColor = 'text-gray-500';
                        
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) {
                            $iconClass = 'fas fa-file-image';
                            $iconColor = 'text-blue-500';
                        } elseif ($extension === 'pdf') {
                            $iconClass = 'fas fa-file-pdf';
                            $iconColor = 'text-red-500';
                        } elseif (in_array($extension, ['doc', 'docx'])) {
                            $iconClass = 'fas fa-file-word';
                            $iconColor = 'text-blue-700';
                        } elseif (in_array($extension, ['xls', 'xlsx'])) {
                            $iconClass = 'fas fa-file-excel';
                            $iconColor = 'text-green-600';
                        }
                    @endphp
                    
                    <a href="{{ asset('/' . $ticket->file_attachment) }}" 
                    target="_blank" 
                    class="inline-flex items-center gap-3 px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                        <i class="{{ $iconClass }} {{ $iconColor }} text-2xl"></i>
                        <div class="text-left">
                            <p class="text-sm font-medium text-gray-900">{{ basename($ticket->file_attachment) }}</p>
                            <p class="text-xs text-gray-500 uppercase">{{ $extension }} file</p>
                        </div>
                        <i class="fas fa-external-link-alt text-gray-400 ml-auto"></i>
                    </a>
                </div>
            @endif
            
            <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                <div><strong>Department:</strong> {{ $ticket->ticketDepartment->name }}</div>
                <div><strong>Assigned To:</strong> {{ $ticket->assignedTo?->name ?? 'Unassigned' }}</div>
                <div><strong>Created By:</strong> {{ $ticket->createdBy->name }}</div>
                <div><strong>Last Updated:</strong> {{ $ticket->updated_at->diffForHumans() }}</div>
            </div>
        </div>

        <!-- Replies Section -->
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-comments mr-2"></i>Replies ({{ $ticket->replies->count() }})
            </h3>
            
            <div id="repliesContainer" class="space-y-4 mb-6">
                @forelse($ticket->replies as $reply)
                    <div class="reply-item bg-gray-50 rounded-lg p-4" data-reply-id="{{ $reply->id }}">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg">
                                    {{ strtoupper(substr($reply->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $reply->user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $reply->created_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                            
                            @if($reply->user_id === auth()->id())
                                <div class="flex gap-2">
                                    <button onclick="editReply({{ $reply->id }}, '{{ addslashes($reply->content) }}')" 
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                            data-action="{{ route('role.support-tickets.replies.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'reply' => $reply->id]) }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteReply({{ $reply->id }})" 
                                            class="text-red-600 hover:text-red-800 text-sm"
                                            data-action="{{ route('role.support-tickets.replies.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'reply' => $reply->id]) }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                        
                        <div class="reply-content text-gray-700 whitespace-pre-line pl-13">
                            {{ $reply->content }}
                        </div>
                        
                        @if($reply->attachment_path)
                            <div class="mt-3 pl-13">
                                <a href="{{ route('role.support-tickets.attachment.download', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'filename' => basename($reply->attachment_path)]) }}" 
                                target="_blank" 
                                class="inline-flex items-center text-blue-600 hover:underline">
                                    <i class="fas fa-paperclip mr-1"></i> View Attachment
                                </a>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-6">No replies yet. Be the first to reply!</p>
                @endforelse
            </div>

            <!-- Reply Form -->
            @if($ticket->status !== 'closed')
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-semibold mb-3">Add Reply</h4>
                    <form id="replyForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <textarea name="content" id="replyContent" rows="4" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                placeholder="Type your reply here..." required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm text-gray-700 mb-2">Attachment (Optional)</label>
                            <input type="file" name="attachment" id="replyAttachment" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <p class="text-xs text-gray-500 mt-1">Max 5MB. Allowed: JPG, PNG, PDF, DOC, DOCX</p>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                <i class="fas fa-paper-plane mr-1"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-gray-100 border rounded-lg p-4 text-center text-gray-600">
                    <i class="fas fa-lock mr-2"></i> This ticket is closed.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Submit reply
    $('#replyForm').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        
        $.ajax({
            url: '{{ route("role.support-tickets.replies.store", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "ticket" => $ticket->id]) }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 1500
                    });
                    setTimeout(() => location.reload(), 1500);
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to add reply.'
                });
            }
        });
    });
});

function editReply(replyId, content) {
    Swal.fire({
        title: 'Edit Reply',
        input: 'textarea',
        inputValue: content,
        inputAttributes: {
            rows: 5
        },
        showCancelButton: true,
        confirmButtonText: 'Update',
        preConfirm: (newContent) => {
            if (!newContent) {
                Swal.showValidationMessage('Reply content is required');
            }
            return newContent;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("role.support-tickets.replies.update", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "reply" => ":replyId"]) }}'.replace(':replyId', replyId),
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    content: result.value
                },
                success: function(response) {
                    Swal.fire('Updated!', response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function() {
                    Swal.fire('Error!', 'Failed to update reply.', 'error');
                }
            });
        }
    });
}

function deleteReply(replyId) {
    Swal.fire({
        title: 'Delete Reply?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("role.support-tickets.replies.destroy", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "reply" => ":replyId"]) }}'.replace(':replyId', replyId),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    Swal.fire('Deleted!', response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function() {
                    Swal.fire('Error!', 'Failed to delete reply.', 'error');
                }
            });
        }
    });
}
</script>
@endsection