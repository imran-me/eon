@extends('layout.app')
@section('meta-information')
    <title>Edit User #{{ $data->name }}</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Container and Card */
        .manage-user-container {
            width: 100%;
            max-width: 100%;
            margin: 1rem auto 2rem auto;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .manage-user-container .form-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        /* Header Styling */
        .manage-user-container .form-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 1.5rem 2rem;
            color: white;
        }

        .manage-user-container .form-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .manage-user-container .form-header p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        /* Body and Sections */
        .manage-user-container .form-body {
            padding: 2rem;
        }

        .manage-user-container .form-section {
            margin-bottom: 2rem;
        }

        .manage-user-container .form-section h3 {
            font-size: 1.1rem;
            color: #374151;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.25rem;
            font-weight: 600;
        }

        .manage-user-container .highlight-section {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #f3f4f6;
        }

        /* Grid System */
        .manage-user-container .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .manage-user-container .tri-column {
            grid-template-columns: repeat(3, 1fr);
        }

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
        .manage-user-container .form-group {
            display: flex;
            flex-direction: column;
        }

        .manage-user-container .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.4rem;
        }

        .manage-user-container .form-group input,
        .manage-user-container .form-group select,
        .manage-user-container .form-group textarea {
            padding: 0.6rem 0.85rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .manage-user-container .form-group input:focus,
        .manage-user-container .form-group select:focus,
        .manage-user-container .form-group textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .manage-user-container .full-width {
            grid-column: span 2;
            margin-top: 1rem;
        }

        /* Alerts */
        .manage-user-container .alert-success {
            background: #ecfdf5;
            color: #065f46;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #10b981;
            margin-bottom: 1.5rem;
        }

        .manage-user-container .alert-error {
            background: #fef2f2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #ef4444;
            margin-bottom: 1.5rem;
        }

        .manage-user-container .alert-error ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        /* Footer and Buttons */
        .manage-user-container .form-footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
        }

        .manage-user-container .btn-save {
            background: #2563eb;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .manage-user-container .btn-save:hover {
            background: #1d4ed8;
        }

        .manage-user-container .btn-save:active {
            transform: translateY(1px);
        }

        /* Permission Button Styles */
        .manage-user-container .btn-permission {
            opacity: 0.6;
        }

        .manage-user-container .btn-permission.active {
            opacity: 1;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            transform: scale(1.05);
        }

        .manage-user-container .btn-permission:hover {
            opacity: 0.9;
        }

        /* Toggle Switch Styles */
        .manage-user-container .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }

        .manage-user-container .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .manage-user-container .toggle-switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 34px;
        }

        .manage-user-container .toggle-switch .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .manage-user-container .toggle-switch input:checked + .slider {
            background-color: #10b981;
        }

        .manage-user-container .toggle-switch input:checked + .slider:before {
            transform: translateX(22px);
        }

        .manage-user-container .toggle-switch input:focus + .slider {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .manage-user-container .toggle-switch label {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        @media (max-width: 768px) {

            .manage-user-container .form-grid,
            .manage-user-container .tri-column {
                grid-template-columns: 1fr;
            }

            .manage-user-container .full-width {
                grid-column: span 1;
            }
        }
    </style>
@endsection
@section('main-content')
    <div class="manage-user-container">
        <div class="form-card">
            <div class="form-header">
                <h2>Update User</h2>
                <p>Enter the employee's personal and professional details.</p>
            </div>

            <div class="form-body">
                {{-- Messages --}}
                @if (session('success'))
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

                <form
                    action="{{ route('role.user.update', ['role' => \Illuminate\Support\Str::slug($data->getRoleNames()->first()), 'user' => $data->id]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="form-section">
                        <h3>Basic Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" name="name" id="name" placeholder="Enter Name" required
                                    value="{{ $data->name }}">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" name="email" id="email" placeholder="Enter Email" required
                                    value="{{ $data->email }}">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="text" name="phone" id="phone" placeholder="Enter Phone" required
                                    value="{{ $data->phone }}">
                            </div>
                            <div class="form-group">
                                <label for="employee_id_no">Employee ID No *</label>
                                <input type="text" name="employee_id_no" id="employee_id_no" placeholder="Employee ID No"
                                    required value="{{ $data->employee_id_no }}" autocomplete="none">
                            </div>
                            <div class="form-group">
                                <label for="image">User Image</label>
                                <input type="file" name="image" class="file-input" id="image" accept="image/*">
                                @if ($data->image)
                                    <img src="{{ asset($data->image) }}"
                                        alt="User Image" style="margin-top: 0.5rem; max-width: 150px; border-radius: 6px;">
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="image">User Status</label>
                                <select name="is_active" class="select2" id="is_active" required>
                                    <option value="1" selected>Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section highlight-section">
                        <h3>Employment & HR Details</h3>
                        <div class="form-grid tri-column">
                            <div class="form-group">
                                <label for="company_id">Company *</label>
                                <select name="company_id" class="select2" id="company_id" required>
                                    {{-- <option value="">Select Company</option> --}}
                                    @foreach (\App\Models\Company::where('status', 1)->get() as $company)
                                        <option value="{{ $company->id }}"
                                            {{ $data->company_id == $company->id ? 'selected' : '' }}>
                                            {{ ucfirst($company->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="department_id">Department *</label>
                                <select name="department_id" class="select2" id="department_id" required>
                                    <option value="">Select Department</option>
                                    @foreach (\App\Models\Department::all() as $dept)
                                        <option value="{{ $dept->id }}"
                                            {{ $data->profile?->department_id == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="designation_id">Designation *</label>
                                <select name="designation_id" class="select2" id="designation_id" required>
                                    <option value="">Select Designation</option>
                                    @foreach (\App\Models\Designation::all() as $desig)
                                        <option value="{{ $desig->id }}"
                                            {{ $data->profile?->designation_id == $desig->id ? 'selected' : '' }}>
                                            {{ $desig->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="employment_type">Employment Type *</label>
                                <select name="employment_type" class="select2" id="employment_type" required>
                                    <option value="full_time"
                                        {{ $data->profile?->employment_type == 'full_time' ? 'selected' : '' }}>Full Time
                                    </option>
                                    <option value="part_time"
                                        {{ $data->profile?->employment_type == 'part_time' ? 'selected' : '' }}>Part Time
                                    </option>
                                    <option value="contractual"
                                        {{ $data->profile?->employment_type == 'contractual' ? 'selected' : '' }}>
                                        Contractual</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="joining_date">Joining Date *</label>
                                <input type="date" name="joining_date" id="joining_date"
                                    value="{{ $data->profile?->joining_date }}" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_template_id">Salary Template</label>
                                <select name="salary_template_id" class="select2" id="salary_template_id">
                                    <option value="">Select Salary Template</option>
                                    @foreach ($salaryTemplates as $template)
                                        <option value="{{ $template->id }}"
                                            {{ $data->salary_template_id == $template->id ? 'selected' : '' }}>
                                            {{ ucfirst($template->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="salary">Monthly Salary *</label>
                                <input type="number" name="salary" id="salary" step="0.01"
                                    value="{{ $data->profile?->salary }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Role & Security</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="roles">System Role <small style="color:#888">(একাধিক role select করা যাবে)</small></label>
                                <select name="roles[]" class="select2" id="roles" multiple="multiple" style="width:100%">
                                    @foreach (\Spatie\Permission\Models\Role::all() as $role)
                                        <option value="{{ $role->name }}"
                                            {{ $data->getRoleNames()->contains($role->name) ? 'selected' : '' }}>
                                            {{ ucfirst($role->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="shift_id">Shift Assignment</label>
                                <select name="shift_id" class="select2" id="shift_id">
                                    <option value="">Select Shift</option>
                                    @foreach (\App\Models\Shift::all() as $shift)
                                        <option value="{{ $shift->id }}"
                                            {{ $data->shift_id == $shift->id ? 'selected' : '' }}>
                                            {{ ucfirst($shift->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="device_id">Device Assign</label>
                                <select name="device_id[]" class="select2" multiple id="device_id">
                                    @foreach ($devices as $device)
                                        <option value="{{ $device->id }}"
                                            {{ in_array($device->id, $data->device_ids ?? []) ? 'selected' : '' }}>
                                            {{ ucfirst($device->name) }} ({{ ucfirst($device->company->name) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Device User IDs</label>
                                <div id="device-user-container"></div>
                            </div>


                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" name="password" id="password" value="">
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    value="">
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Residential Address</label>
                        <textarea name="address" id="address" rows="2">{{ $data->address }}</textarea>
                    </div>

                    <div class="form-section">
                        <h3>Permissions</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 1rem;">
                                    <span>Manual Attendance Check-in/Check-out</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="allow_manual_attendance" name="allow_manual_attendance" 
                                            value="1" {{ $data->allow_manual_attendance ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </div>
                                </label>
                                <small style="display: block; margin-top: 0.5rem; color: #6b7280;" id="status-text">
                                    {{ $data->allow_manual_attendance ? '✓ Currently enabled' : '✗ Currently disabled' }}
                                </small>
                            </div>
                            <div class="form-group">
                                <label style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 1rem;">
                                    <span>Overtime Salary Eligible</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="overtime_eligible" name="overtime_eligible"
                                            value="1" {{ $data->overtime_eligible ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </div>
                                </label>
                                <small style="display: block; margin-top: 0.5rem; color: #6b7280;" id="overtime-status-text">
                                    {{ $data->overtime_eligible ? '✓ Currently enabled' : '✗ Currently disabled' }}
                                </small>
                            </div>
                            <div class="form-group">
                                <label style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 1rem;">
                                    <span>Auto-generate Monthly Payslip</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="auto_payslip_enabled" name="auto_payslip_enabled"
                                            value="1" {{ $data->auto_payslip_enabled ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </div>
                                </label>
                                <small style="display: block; margin-top: 0.5rem; color: #6b7280;" id="auto-payslip-status-text">
                                    {{ $data->auto_payslip_enabled ? '✓ Currently enabled' : '✗ Currently disabled' }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn-save">Save User Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- <div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Add User</h2>
    
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-100 text-green-800 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-4 py-3">
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('role.user.update', ['role' => \Illuminate\Support\Str::slug($data->getRoleNames()->first()), 'user' => $data->id]) }}" method="POST">
        @csrf
        @method('PUT')
        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700">Company</label>
            <select name="company_id" id="company_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Company</option>
                @foreach (\App\Models\Company::all() as $company)
                    <option value="{{ $company->id }}" {{ $data->company_id == $company->id ? ' selected' : '' }}>{{ ucfirst($company->name) }}</option>
                @endforeach
            </select>
        </div>

        
        <div class="mb-4">
            <label for="shift_id" class="block text-sm font-medium text-gray-700">Shift</label>
            <select name="shift_id" id="shift_id"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Shift</option>
                @foreach (\App\Models\Shift::all() as $shift)
                    <option value="{{ $shift->id }}" {{ $data->shift_id == $shift->id ? ' selected' : '' }}>{{ ucfirst($shift->name) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" name="name" id="name" required value="{{$data->name}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Device User Id -->
        <div class="mb-4">
            <label for="device_user_id" class="block text-sm font-medium text-gray-700">Device User Id</label>
            <input type="text" name="device_user_id" id="device_user_id" required value="{{$data->device_user_id}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Email -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" required value="{{$data->email}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Phone -->
        <div class="mb-4">
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" id="phone" required value="{{$data->phone}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Address -->
        <div class="mb-4">
            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" id="address" rows="3"
                      class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{$data->address}}</textarea>
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" id="password"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Role -->
        <div class="mb-4">
            <label for="roles" class="block text-sm font-medium text-gray-700">Role <small style="color:#888">(একাধিক role select করা যাবে)</small></label>
            <select name="roles[]" id="roles" multiple="multiple"
                    class="select2 mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                @foreach (\Spatie\Permission\Models\Role::all() as $role)
                    <option value="{{ $role->name }}" {{ $data->getRoleNames()->contains($role->name) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                Save
            </button>
        </div>
    </form>
</div> --}}
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $('.select2').select2();
    </script>
    <script>
        $(document).ready(function() {
            $('#device_id').select2();

            function renderDeviceUserInputs() {
                let selectedDevices = $('#device_id').val() || [];
                let container = $('#device-user-container');

                container.html('');

                selectedDevices.forEach(function(deviceId) {
                    let value = deviceUserMap[deviceId] ?? '';

                    container.append(`
                <div class="mb-2" id="device-${deviceId}">
                    <label>Device User ID</label>
                    <input type="text"
                           name="device_user_id[${deviceId}]"
                           class="form-control"
                           value="${value}"
                           placeholder="Enter Device User ID">
                </div>
            `);
                });
            }

            // On change
            $('#device_id').on('change', function() {
                renderDeviceUserInputs();
            });

            // Initial render for edit page
            renderDeviceUserInputs();
        });
    </script>

    <script>
        const deviceUserMap = @json($device_users->pluck('device_user_id', 'device_id'));
    </script>
    <script>
        // Manual Attendance Permission Toggle
        document.getElementById('allow_manual_attendance').addEventListener('change', function(e) {
            let isEnabled = this.checked;
            let statusText = isEnabled ? '✓ Currently enabled' : '✗ Currently disabled';
            document.getElementById('status-text').textContent = statusText;
        });

        // Overtime Eligible Permission Toggle
        document.getElementById('overtime_eligible').addEventListener('change', function(e) {
            let isEnabled = this.checked;
            let statusText = isEnabled ? '✓ Currently enabled' : '✗ Currently disabled';
            document.getElementById('overtime-status-text').textContent = statusText;
        });

        // Auto-generate Monthly Payslip Permission Toggle
        document.getElementById('auto_payslip_enabled').addEventListener('change', function(e) {
            let isEnabled = this.checked;
            let statusText = isEnabled ? '✓ Currently enabled' : '✗ Currently disabled';
            document.getElementById('auto-payslip-status-text').textContent = statusText;
        });
    </script>
    <script>

    $('#salary_template_id').on('change', function () {
        let templateId = $(this).val();
        let role = "{{ request()->route('role') }}";

        if (!templateId) {
            $('#salary').val(0);
            return;
        }

        let url = "{{ route('role.salary.template.get', ['role' => '__role__', 'id' => '__id__']) }}"
                    .replace('__role__', role)
                    .replace('__id__', templateId);

        $.get(url, function (response) {
            $('#salary').val(response.data.total_salary);
        });
    });
</script>
@endsection
