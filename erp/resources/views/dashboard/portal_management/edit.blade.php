@extends('layout.app')

@section('meta-information')
    <title>Edit Portal</title>
@endsection

@section('main-content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 42px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        position: absolute;
        top: 1px;
        right: 3px;
        width: 20px;
    }
    /* Container styling */
    .bg-white.p-6 {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        max-width: 800px;
        margin: 1rem auto 2rem auto;
        border: 1px solid #eef2f7;
    }

    /* Header styling */
    .bg-white.p-6 h2.text-xl {
        color: #1e293b;
        font-size: 1.5rem;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 1rem;
        margin-bottom: 1.5rem !important;
    }

    /* Label improvements */
    .bg-white.p-6 label {
        display: block;
        font-weight: 600;
        font-size: 0.875rem;
        color: #475569;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    /* Input & Select field styling */
    .bg-white.p-6 .w-full.border {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        transition: all 0.2s ease-in-out;
        color: #334155;
    }

    /* Focus states for accessibility and flair */
    .bg-white.p-6 .w-full.border:focus {
        outline: none;
        border-color: #2563eb;
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    /* Spacing between fields */
    .bg-white.p-6 .mb-3 {
        margin-bottom: 1.25rem !important;
    }

    /* Button transformation */
    .bg-white.p-6 button.bg-blue-600 {
        width: 100%;
        padding: 0.75rem 1rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        border: none;
        transition: transform 0.1s transform, box-shadow 0.2s;
        margin-top: 1rem;
    }

    .bg-white.p-6 button.bg-blue-600:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .bg-white.p-6 button.bg-blue-600:active {
        transform: scale(0.98);
    }
</style>
<div class="bg-white p-6">
    <div style="display: flex; align-items: center; justify-content: space-between; padding-botom: 10px; margin-bottom: 15px;">
        <h2 class="text-xl font-bold mb-0" style="padding-bottom: 0 !important; margin-bottom: 0 !important;">Edit Portal</h2>
        <a class="px-4 py-2 bg-blue-600 text-white rounded cursor-pointer" href="{{ route('role.portal-management.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"><i class="fas fa-list"></i> Portal List</a>
    </div>

    <form action="{{ route('role.portal-management.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'portal_management' => $portal->id]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="">Account</label>
            <select id="create_account_id" name="account_id" class="select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%" required>
                <option value="">-- Select --</option>
                @foreach ($accounts as $account)                                    
                <option value="{{ $account->id }}" {{ $portal->account_id == $account->id ? 'selected' : '' }}>{{ $account->name }} ({{ $account->type }})</option>
                @endforeach
            </select>
        </div>

        <div style="display: grid; gap: 20px; grid-template-columns: repeat(2, 1fr)">

            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="name" required class="w-full border px-3 py-2 rounded" value="{{ old('name', $portal->name) }}">
            </div>

            <div class="mb-3">
                <label>Base URL</label>
                <input type="text" name="base_url" class="w-full border px-3 py-2 rounded" value="{{ old('base_url', $portal->base_url) }}">
            </div>

            <div class="mb-3">
                <label>API Key</label>
                <input type="text" name="api_key" class="w-full border px-3 py-2 rounded" value="{{ old('api_key', $portal->api_key) }}">
            </div>

            <div class="mb-3">
                <label>API Secret</label>
                <input type="text" name="api_secret" class="w-full border px-3 py-2 rounded" value="{{ old('api_secret', $portal->api_secret) }}">
            </div>


            <div class="mb-3">
                <label>Type</label>
                <select name="type" class="w-full border px-3 py-2 rounded">
                    <option value="flight" {{ $portal->type == 'flight' ? 'selected' : '' }}>Flight</option>
                    <option value="bus" {{ $portal->type == 'bus' ? 'selected' : '' }}>Bus</option>
                    <option value="train" {{ $portal->type == 'train' ? 'selected' : '' }}>Train</option>
                </select>
            </div>

            {{-- <div class="mb-3">
                <label>Vendor</label>
                <select name="vendor_id" class="w-full border px-3 py-2 rounded">
                    <option value="">-- Select Vendor --</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}" {{ $portal->vendor_id == $vendor->id ? 'selected' : '' }}>
                            {{ $vendor->name }}
                        </option>
                    @endforeach
                </select>
            </div> --}}

            <div class="mb-3">
                <label>Status</label>
                <select name="status" class="w-full border px-3 py-2 rounded">
                    <option value="active" {{ $portal->status == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $portal->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

        </div>

        <div class="mt-0">
            <button class="px-4 py-2 bg-blue-600 text-white rounded cursor-pointer">Update</button>
        </div>
    </form>
</div>
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $('.select2').select2();
    </script>
@endsection
