@extends('layout.app')
@section('meta-information')
    <title>Edit Promotion</title>
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
<style>
    /* Container styling */
    .promotion-form {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        padding: 1.5rem;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Full width for specific rows */
    .promotion-form > div:nth-child(1), /* Employee */
    .promotion-form > div:nth-last-child(2), /* Reason */
    .promotion-form > div:last-child { /* Submit */
        grid-column: span 2;
    }

    /* Label styling */
    .promotion-form .block {
        display: block;
        font-size: 0.875rem;
        color: #374151;
        font-weight: 600;
    }

    /* Form Control styling */
    .promotion-form .form-control {
        width: 100%;
        padding: 0.625rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .promotion-form .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Submit Button styling */
    .promotion-form .btn-submit {
        background-color: #2563eb;
        color: white;
        padding: 0.625rem 1.25rem;
        border-radius: 0.375rem;
        font-weight: 600;
        transition: background-color 0.2s;
        cursor: pointer;
        border: none;
    }

    .promotion-form .btn-submit:hover {
        background-color: #1d4ed8;
    }

    /* Small helper text */
    .promotion-form small {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
    }
</style>
@endsection
@section('main-content')   
<div class="manage-user-container">
    <div class="form-card">
        <div class="form-header">
            <h2>Update Promotion</h2>
            <p>Enter the employee's personal and professional details.</p>
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

            <form action="{{ route('role.promotions.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'promotion' => $promotion->id]) }}" method="POST" class="promotion-form">                            
                @csrf
                @method('PUT')

                {{-- Employee --}}
                <div>
                    <label class="block font-medium mb-1">Employee*</label>
                    <select name="user_id" class="form-control select2" onchange="manageProfile()" required>
                        <option value="">Select Employee</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $promotion->user_id == $user->id ? 'selected' : '' }} data-has_profile="{{ empty($user->profile) ? 0 : 1 }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('user_id') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>
                   
                <div class="has-no-profile" style="display: {{ $promotion->user->profile ? 'none' : 'block' }}">
                    <label class="block font-medium mb-1">Previous Department*</label>
                    <select name="previous_department_id" class="form-control select2" style="width: 100%;">
                        <option value="">Select Department</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ $promotion->previous_department_id == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('previous_department_id') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>
                
                {{-- New Department --}}
                <div>
                    <label class="block font-medium mb-1">New Department*</label>
                    <select name="new_department_id" class="form-control select2" required>
                        <option value="">Select Department</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ $promotion->new_department_id == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('new_department_id') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>


                <div class="has-no-profile" style="display: {{ $promotion->user->profile ? 'none' : 'block' }}">
                    <label class="block font-medium mb-1">Previous Designation*</label>
                    <select name="previous_designation_id" class="form-control select2" style="width: 100%;">
                        <option value="">Select Designation</option>
                        @foreach($designations as $designation)
                            <option value="{{ $designation->id }}" {{ $promotion->previous_designation_id == $designation->id ? 'selected' : '' }}>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                    @error('previous_designation_id') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>

                {{-- New Designation --}}
                <div>
                    <label class="block font-medium mb-1">New Designation*</label>
                    <select name="new_designation_id" class="form-control select2" required>
                        <option value="">Select Designation</option>
                        @foreach($designations as $designation)
                            <option value="{{ $designation->id }}" {{ $promotion->new_designation_id == $designation->id ? 'selected' : '' }}>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                    @error('new_designation_id') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>

                {{-- New Salary --}}
                <div>
                    <label class="block font-medium mb-1">New Salary</label>
                    <input type="number" step="0.01" name="new_salary" value="{{ $promotion->new_salary }}" class="form-control" placeholder="Leave empty to keep current salary">
                    @error('new_salary') <small class="text-red-600">{{ $message }}</small> @enderror
                </div>

                {{-- Effective Date --}}
                <div>
                    <label class="block font-medium mb-1">Effective From*</label>
                    <input type="date" name="effective_from" class="form-control" value="{{ $promotion->effective_from?->format('Y-m-d') }}" required>
                    <small class="text-gray-500">
                        Leave empty for immediate effect after approval
                    </small>
                </div>

                {{-- Reason --}}
                <div>
                    <label class="block font-medium mb-1">Reason</label>
                    <textarea name="reason" rows="3" class="form-control" placeholder="Optional reason">{{ $promotion->reason }}</textarea>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end">
                    <button class="btn btn-primary btn-submit">Update Promotion</button>
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
                
        function manageProfile() {
            // Select the dropdown and the currently selected option
            const select = document.querySelector('select[name="user_id"]');
            const selectedOption = select.options[select.selectedIndex];
            
            // Get the status from your data-attribute (0 or 1)
            const hasProfile = selectedOption.getAttribute('data-has_profile');
            
            // Select all divs with the .has-no-profile class
            const noProfileDivs = document.querySelectorAll('.promotion-form .has-no-profile');

            noProfileDivs.forEach(div => {
                // If hasProfile is "0" (empty), show the fields. Otherwise, hide them.
                if (hasProfile === "0") {
                    div.style.display = "block";
                    // Make inputs required if visible to ensure data integrity
                    div.querySelectorAll('select').forEach(s => s.setAttribute('required', 'required'));
                } else {
                    div.style.display = "none";
                    // Remove required if hidden
                    div.querySelectorAll('select').forEach(s => s.removeAttribute('required'));
                }
            });
        }
    </script>
@endsection