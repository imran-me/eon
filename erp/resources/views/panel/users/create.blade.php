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
            <h2>Add New User</h2>
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

            <form action="{{ route('role.user.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                @csrf

                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" name="name" id="name" placeholder="Enter Name" required value="{{old('name')}}">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" name="email" id="email" placeholder="Enter Email" required value="{{old('email')}}">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="text" name="phone" id="phone" placeholder="Enter Phone" required value="{{old('phone')}}">
                        </div>
                        {{-- <div class="form-group">
                            <label for="device_user_id">Device User ID *</label>
                            <input type="text" name="device_user_id" id="device_user_id" placeholder="Device User Id" required value="{{old('device_user_id')}}">
                        </div> --}}
                        <div class="form-group">
                            <label for="image">User Image</label>
                            <input type="file" name="image" class="file-input" id="image" accept="image/*">
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
                                @foreach(\App\Models\Company::where('status', 1)->get() as $company)
                                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ ucfirst($company->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department *</label>
                            <select name="department_id" class="select2" id="department_id" required>
                                <option value="">Select Department</option>
                                @foreach(\App\Models\Department::all() as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="designation_id">Designation *</label>
                            <select name="designation_id" class="select2" id="designation_id" required>
                                <option value="">Select Designation</option>
                                @foreach(\App\Models\Designation::all() as $desig)
                                    <option value="{{ $desig->id }}" {{ old('designation_id') == $desig->id ? 'selected' : '' }}>{{ $desig->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="employment_type">Employment Type *</label>
                            <select name="employment_type" class="select2" id="employment_type" required>
                                <option value="full_time" {{ old('employment_type') == 'full_time' ? 'selected' : '' }}>Full Time</option>
                                <option value="part_time" {{ old('employment_type') == 'part_time' ? 'selected' : '' }}>Part Time</option>
                                <option value="contractual" {{ old('employment_type') == 'contractual' ? 'selected' : '' }}>Contractual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="joining_date">Joining Date *</label>
                            <input type="date" name="joining_date" id="joining_date" value="{{old('joining_date')}}" required>
                        </div>
                        <div class="form-group">
                            <label for="salary_template_id">Salary Template</label>
                            <select name="salary_template_id" class="select2" id="salary_template_id" required>
                                <option value="">Select Salary Template</option>
                                @foreach($salaryTemplates as $template)
                                    <option value="{{ $template->id }}" {{ old('salary_template_id') == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="salary">Monthly Salary *</label>
                            <input type="number" name="salary" id="salary" step="0.01" value="{{old('salary', 0)}}">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Role & Security</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="roles">System Role <small style="color:#888">(একাধিক role select করা যাবে)</small></label>
                            <select name="roles[]" class="select2" id="roles" multiple="multiple" style="width:100%">
                                @foreach(\Spatie\Permission\Models\Role::all() as $role)
                                    <option value="{{ $role->name }}" {{ in_array($role->name, old('roles', [])) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="shift_id">Shift Assignment</label>
                            <select name="shift_id" class="select2" id="shift_id">
                                <option value="">Select Shift</option>
                                @foreach(\App\Models\Shift::all() as $shift)
                                    <option value="{{ $shift->id }}" {{ old('shift_id') == $shift->id ? 'selected' : '' }}>{{ ucfirst($shift->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" name="password" id="password" value="12345678" required>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm Password *</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" value="12345678" required>
                        </div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="address">Residential Address</label>
                    <textarea name="address" id="address" rows="2">{{old('address')}}</textarea>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn-save">Save User Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

{{-- @section('main-content')
<div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Add User</h2>
    @if(session('success'))
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
    <form action="{{ route('role.user.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
        @csrf

        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700">Company</label>
            <select name="company_id" id="company_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Company</option>
                @foreach(\App\Models\Company::all() as $company)
                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? ' selected' : '' }}>{{ ucfirst($company->name) }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label for="shift_id" class="block text-sm font-medium text-gray-700">Shift</label>
            <select name="shift_id" id="shift_id"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Shift</option>
                @foreach(\App\Models\Shift::all() as $shift)
                    <option value="{{ $shift->id }}" {{ old('shift_id') == $shift->id ? ' selected' : '' }}>{{ ucfirst($shift->name) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Name -->
        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" name="name" id="name" required value="{{old('name')}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Device User Id -->
        <div class="mb-4">
            <label for="device_user_id" class="block text-sm font-medium text-gray-700">Device User Id</label>
            <input type="text" name="device_user_id" id="device_user_id" required value="{{old('device_user_id')}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Email -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" required value="{{old('email')}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Phone -->
        <div class="mb-4">
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" id="phone" required value="{{old('phone')}}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Address -->
        <div class="mb-4">
            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" id="address" rows="3"
                      class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{old('address')}}</textarea>
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" id="password" required
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Role -->
        <div class="mb-4">
            <label for="roles" class="block text-sm font-medium text-gray-700">Role <small style="color:#888">(একাধিক role select করা যাবে)</small></label>
            <select name="roles[]" id="roles" multiple="multiple"
                    class="select2 mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                @foreach(\Spatie\Permission\Models\Role::all() as $role)
                    <option value="{{ $role->name }}" {{ in_array($role->name, old('roles', [])) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
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
</div>
@endsection --}}

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $('.select2').select2();

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