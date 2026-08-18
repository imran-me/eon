@extends('layout.app')

@section('meta-information')
    <title>Edit Passport Holder</title>
@endsection

@section('main-content')
<div class="bg-white p-6">
    <h2 class="text-xl font-bold mb-4">Edit Passport Holder</h2>

    <form action="{{ route('role.passport-holder.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder' => $passportHolder->id]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" required class="w-full border px-3 py-2 rounded" value="{{ old('name', $passportHolder->name) }}">
        </div>

        <div class="mb-3">
            <label>Passport Number</label>
            <input type="text" name="passport_no" required class="w-full border px-3 py-2 rounded" value="{{ old('passport_no', $passportHolder->passport_no) }}">
        </div>

        <div class="mb-3">
            <label>Nationality</label>
            <input type="text" name="nationality" required class="w-full border px-3 py-2 rounded" value="{{ old('nationality', $passportHolder->nationality) }}">
        </div>

        <div class="mb-3">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" required class="w-full border px-3 py-2 rounded" value="{{ old('date_of_birth', $passportHolder->date_of_birth) }}">
        </div>

        <div class="mb-3">
            <label>Issue Date</label>
            <input type="date" name="issue_date" required class="w-full border px-3 py-2 rounded" value="{{ old('issue_date', $passportHolder->issue_date) }}">
        </div>

        <div class="mb-3">
            <label>Expiry Date</label>
            <input type="date" name="expiry_date" required class="w-full border px-3 py-2 rounded" value="{{ old('expiry_date', $passportHolder->expiry_date) }}">
        </div>

        <div class="mb-3">
            <label>Category</label>
            <select name="category_id" class="w-full border px-3 py-2 rounded">
                <option value="">-- Select Category --</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('category_id', $passportHolder->category_id) == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="w-full border px-3 py-2 rounded">
                <option value="active" {{ old('status', $passportHolder->status) == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $passportHolder->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="mt-4">
            <button class="px-4 py-2 bg-blue-600 text-white rounded cursor-pointer">Update</button>
        </div>
    </form>
</div>
@endsection
