@extends('layout.app')
@section('meta-information')
    <title>User Create</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Container and Card */
    .manage-user-container { width: 100%; max-width: 100%; margin: 1rem auto 2rem auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .manage-user-container .form-card { background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb; }

    /* Header Styling */
    .manage-user-container .form-header { background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 1.5rem 2rem; color: white; }
    .manage-user-container .form-header h2 { margin: 0; font-size: 1.5rem; font-weight: 600; }
    .manage-user-container .form-header p { margin: 0.25rem 0 0; font-size: 0.875rem; opacity: 0.9; }

    /* Body and Sections */
    .manage-user-container .form-body { padding: 2rem; }
    .manage-user-container .form-section { margin-bottom: 2rem; }
    .manage-user-container .form-section h3 { font-size: 1.1rem; color: #374151; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; margin-bottom: 1.25rem; font-weight: 600; }
    .manage-user-container .highlight-section { background: #f9fafb; padding: 1.5rem; border-radius: 8px; border: 1px solid #f3f4f6; }

    /* Grid System */
    .manage-user-container .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .manage-user-container .tri-column { grid-template-columns: repeat(3, 1fr); }

    .manage-user-container .file-input {
        padding: 0.5rem;
        background: #f3f4f6;
        border: 2px dashed #d1d5db;
        cursor: pointer;
    }

    .manage-user-container .file-input:hover {
        background: #e5e7eb;
        border-color: #3b82f6;
    }
    /* Form Controls */
    .manage-user-container .form-group { display: flex; flex-direction: column; }
    .manage-user-container .form-group label { font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.4rem; }
    .manage-user-container .form-group input, 
    .manage-user-container .form-group select, 
    .manage-user-container .form-group textarea { 
        padding: 0.6rem 0.85rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; transition: border-color 0.2s, box-shadow 0.2s; outline: none; 
    }
    .manage-user-container .form-group input:focus, 
    .manage-user-container .form-group select:focus, 
    .manage-user-container .form-group textarea:focus { 
        border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); 
    }
    .manage-user-container .full-width { grid-column: span 2; margin-top: 1rem; }

    /* Alerts */
    .manage-user-container .alert-success { background: #ecfdf5; color: #065f46; padding: 1rem; border-radius: 6px; border-left: 4px solid #10b981; margin-bottom: 1.5rem; }
    .manage-user-container .alert-error { background: #fef2f2; color: #991b1b; padding: 1rem; border-radius: 6px; border-left: 4px solid #ef4444; margin-bottom: 1.5rem; }
    .manage-user-container .alert-error ul { margin: 0; padding-left: 1.2rem; }

    /* Footer and Buttons */
    .manage-user-container .form-footer { border-top: 1px solid #e5e7eb; padding-top: 1.5rem; display: flex; justify-content: flex-end; }
    .manage-user-container .btn-save { 
        background: #2563eb; color: white; padding: 0.75rem 2rem; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; transition: background 0.2s, transform 0.1s; 
    }
    .manage-user-container .btn-save:hover { background: #1d4ed8; }
    .manage-user-container .btn-save:active { transform: translateY(1px); }

    /* Responsive */
    @media (max-width: 768px) {
        .manage-user-container .form-grid, 
        .manage-user-container .tri-column { grid-template-columns: 1fr; }
        .manage-user-container .full-width { grid-column: span 1; }
    }
</style> 
@endsection
@section('main-content')   
<div class="manage-user-container">
    <div class="form-card">
        <div class="form-header">
            <h2>Add New Resignation</h2>
            <p>Enter the employee's resignation details.</p>
        </div>

        <div class="form-body">
            {{-- Messages --}}
            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('role.resignations.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="employee_id">Employee Name *</label>
                            <select name="employee_id" class="select2" id="employee_id" required>
                                {{-- <option value="">Select Company</option> --}}
                                @foreach($employees as $item)
                                    <option value="{{ $item->id }}" {{ old('employee_id') == $item->id ? 'selected' : '' }}>{{ ucfirst($item->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="resign_date">Resign Date*</label>
                            <input type="date" name="resign_date" id="resign_date" placeholder="Enter Resign Date" required value="{{old('resign_date')}}">
                        </div>
                        <div class="form-group">
                            <label for="last_working_day">Last Working day *</label>
                            <input type="date" name="last_working_day" id="last_working_day" placeholder="Enter Last Working Day" required value="{{old('last_working_day')}}">
                        </div>
                        <div class="form-group">
                            <label for="image">Resign Type</label>
                            <select name="resign_type" class="select2" id="resign_type" required>                                
                                <option value="voluntary" selected>Voluntary</option>
                                <option value="termination">Termination</option>
                                <option value="abscond">Abscond</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason  *</label>
                            <select name="reason" id="reason" required>
                                <option value="personal" {{ old('reason') == 'personal' ? 'selected' : '' }}>Personal</option>
                                <option value="health" {{ old('reason') == 'health' ? 'selected' : '' }}>Health</option>
                                <option value="better_opportunity" {{ old('reason') == 'better_opportunity' ? 'selected' : '' }}>Better Opportunity</option>
                                <option value="company_policy_issue" {{ old('reason') == 'company_policy_issue' ? 'selected' : '' }}>Company Policy Issue</option>
                                <option value="career_change" {{ old('reason') == 'career_change' ? 'selected' : '' }}>Career Change</option>
                                <option value="relocation" {{ old('reason') == 'relocation' ? 'selected' : '' }}>Relocation</option>
                                <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notice_period_days">Notice Period Days *</label>
                            <input type="number" name="notice_period_days" id="notice_period_days" placeholder="Notice Period Days" value="{{old('notice_period_days')}}">
                        </div>
                        <div class="form-group">
                            <label for="approved_at">Approved At</label>
                            <input type="date" name="approved_at" id="approved_at" placeholder="Enter Approved At" value="{{old('approved_at')}}">
                        </div>
                        <div class="form-group">
                            <label for="exit_note">Exit Note</label>
                            <textarea type="text" name="exit_note" id="exit_note" placeholder="Enter Exit Note">{{old('exit_note')}}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" class="select2" id="status" required>
                                <option value="pending" selected>Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Documents</h3>
                    <div id="create_attachments_wrapper" class="space-y-1"></div>
                    <button type="button" id="create_add_attachment" class="mt-1 text-xs text-blue-600 hover:underline">
                        <i class="fas fa-plus"></i> Add File
                    </button>
                    <p class="text-xs text-gray-400 mt-1">jpg, jpeg, png, pdf, doc, docx · max 10MB each</p>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn-save">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection



@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $('.select2').select2();

        function createAttachmentRow(wrapper) {
            const row = $(
                '<div class="flex items-center gap-2 mb-1">' +
                    '<input type="file" name="attachments[]" class="form-control w-full border border-gray-300 rounded-md p-2" />' +
                    '<button type="button" class="remove-attachment btn btn-danger px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600">Remove</button>' +
                '</div>'
            );
            $(wrapper).append(row);
        }

        $('#create_add_attachment').click(function() { createAttachmentRow('#create_attachments_wrapper'); });
        $(document).on('click', '.remove-attachment', function() { $(this).closest('div').remove(); });

        createAttachmentRow('#create_attachments_wrapper');
    </script>
@endsection