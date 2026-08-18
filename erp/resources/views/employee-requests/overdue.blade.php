@extends('layout.app')
@section('meta-information')
    <title>Overdue Requirements</title>
@endsection
@section('main-content')

<div class="states-table-container" style="background:white;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,0.08);overflow:hidden">
    <div class="states-table-header px-6 py-4 flex justify-between items-center" style="background:linear-gradient(90deg,#7c2d12,#b91c1c)">
        <h2 class="text-white text-lg font-semibold">
            <i class="fas fa-exclamation-triangle mr-2"></i> Overdue Requirements
        </h2>
        <a href="{{ route('role.employee-requests.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="btn btn-sm btn-light">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    <div class="table-responsive" style="padding:16px">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                    <th>Escalated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($overdue as $key => $assignment)
                <tr>
                    <td>{{ ($overdue->currentPage() - 1) * $overdue->perPage() + $key + 1 }}</td>
                    <td>{{ $assignment->assignedTo?->name }}</td>
                    <td>{{ \App\Models\EmployeeRequest::requestTypeLabel($assignment->request?->request_type) }}</td>
                    <td class="text-danger fw-bold">{{ $assignment->due_date?->format('d M Y') }}</td>
                    <td>
                        <span class="badge bg-danger text-white">
                            {{ $assignment->due_date?->diffInDays(now()) }} days
                        </span>
                    </td>
                    <td>
                        @if($assignment->escalated)
                            <span class="badge" style="background:#fef3c7;color:#92400e">⚠️ Escalated</span>
                        @else
                            <span class="badge" style="background:#f3f4f6;color:#6b7280">—</span>
                        @endif
                    </td>
                    <td>
                        @if(!$assignment->escalated)
                        @can('escalate require assignment')
                        <button class="btn btn-sm btn-warning escalate-btn" data-id="{{ $assignment->id }}">
                            <i class="fas fa-exclamation-triangle"></i> Escalate
                        </button>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-check-circle fa-2x text-success mb-3 d-block"></i>
                        <span class="text-muted">No overdue requirements</span>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3 border-top">
        {{ $overdue->links() }}
    </div>
</div>

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    $(document).on('click', '.escalate-btn', function () {
        const id = $(this).data('id');
        const url = '{{ route("role.require-assignments.escalate", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "id" => "__ID__"]) }}'.replace('__ID__', id);
        Swal.fire({ title: 'Escalate this requirement?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Escalate', confirmButtonColor: '#f59e0b' })
            .then((r) => {
                if (r.isConfirmed) {
                    $.ajax({ url, method: 'PUT', success: function (res) {
                        if (res.success) { Swal.fire('Done', res.message, 'success'); setTimeout(() => location.reload(), 800); }
                        else { Swal.fire('Error', res.message, 'error'); }
                    }});
                }
            });
    });
});
</script>
@endsection
