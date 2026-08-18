@extends('layout.app')

@section('meta-information')
    <title>Edit Bank</title>
@endsection

@section('main-content')
<div class="bg-white p-6">
    <h2 class="text-xl font-bold mb-4">Edit Bank</h2>

    {{-- Error Display --}}
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            <strong>Whoops!</strong> Please fix the following errors:
            <ul class="list-disc pl-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.banks.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank' => $bank->id]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="block">Bank Name *</label>
            <input type="text" name="name" class="w-full border px-3 py-2 rounded" value="{{ old('name', $bank->name) }}" required>
        </div>

        <div class="mb-3">
            <label class="block">Branch Name</label>
            <input type="text" name="branch_name" class="w-full border px-3 py-2 rounded" value="{{ old('branch_name', $bank->branch_name) }}">
        </div>

        <div class="mb-3">
            <label class="block">Account Name *</label>
            <input type="text" name="account_name" class="w-full border px-3 py-2 rounded" value="{{ old('account_name', $bank->account_name) }}" required>
        </div>

        <div class="mb-3">
            <label class="block">Account Type *</label>
            <select name="account_type" class="w-full border px-3 py-2 rounded" required>
                <option value="">-- Select Type --</option>
                <option value="savings" {{ old('account_type', $bank->account_type) == 'savings' ? 'selected' : '' }}>Savings</option>
                <option value="current" {{ old('account_type', $bank->account_type) == 'current' ? 'selected' : '' }}>Current</option>
                <option value="fixed" {{ old('account_type', $bank->account_type) == 'fixed' ? 'selected' : '' }}>Fixed</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="block">Bank Type *</label>
            <select name="bank_type" class="w-full border px-3 py-2 rounded" required>
                <option value="">-- Select Type --</option>
                <option value="national" {{ old('bank_type', $bank->bank_type) == 'national' ? 'selected' : '' }}>National</option>
                <option value="international" {{ old('bank_type', $bank->bank_type) == 'international' ? 'selected' : '' }}>International</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="block">Payment Type *</label>
            <select name="type" class="w-full border px-3 py-2 rounded" required>
                <option value="">-- Select Type --</option>
                <option value="bank" {{ old('type', $bank->type) == 'bank' ? 'selected' : '' }}>Bank</option>
                <option value="mobile_banking" {{ old('type', $bank->type) == 'mobile_banking' ? 'selected' : '' }}>Mobile Banking</option>
                <option value="digital_wallet" {{ old('type', $bank->type) == 'digital_wallet' ? 'selected' : '' }}>Digital Wallet</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="block">Routing Number</label>
            <input type="text" name="routing_number" class="w-full border px-3 py-2 rounded" value="{{ old('routing_number', $bank->routing_number) }}">
        </div>

        <div class="mb-3">
            <label class="block">Account Number *</label>
            <input type="text" name="account_number" class="w-full border px-3 py-2 rounded" value="{{ old('account_number', $bank->account_number) }}" required>
        </div>

        <div class="mb-3">
            <label class="block">IBAN</label>
            <input type="text" name="iban" class="w-full border px-3 py-2 rounded" value="{{ old('iban', $bank->iban) }}">
        </div>

        <div class="mb-3">
            <label class="block">SWIFT Code</label>
            <input type="text" name="swift_code" class="w-full border px-3 py-2 rounded" value="{{ old('swift_code', $bank->swift_code) }}">
        </div>

        <div class="mb-3">
            <label class="block">Currency *</label>
            <input type="text" name="currency" class="w-full border px-3 py-2 rounded" value="{{ old('currency', $bank->currency) }}" required>
        </div>

        <div class="mb-3">
            <label class="block">Address</label>
            <textarea name="address" class="w-full border px-3 py-2 rounded" rows="2">{{ old('address', $bank->address) }}</textarea>
        </div>

        <div class="mb-3">
            <label class="block">Initial Balance *</label>
            <input type="number" step="0.01" min="0" name="balance" class="w-full border px-3 py-2 rounded" value="{{ old('balance', $bank->balance) }}" required>
        </div>

        <div class="mt-4 flex gap-3">
            <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 cursor-pointer">Update</button>
            <a href="{{ route('role.banks.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 cursor-pointer">Cancel</a>
        </div>
    </form>
</div>
@endsection
