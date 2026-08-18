@extends('layout.app')

@section('meta-information')
    <title>Edit Customer</title>
@endsection

@section('main-content')
<div class="min-h-screen bg-gray-50 p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Customer</h1>
            <p class="text-sm text-gray-500 mt-0.5">Epal Group ERP &rsaquo; Customer Management &rsaquo; {{ $customer->name }}</p>
        </div>
        <a href="{{ route('role.customer.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to List
        </a>
    </div>

    {{-- Alerts --}}
    @if (session('success'))
        <div class="mb-4 flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 flex items-start gap-3 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.customer.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'customer' => $customer->id]) }}"
          method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: Main Info --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Basic Information Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-gray-700">Basic Information</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                        {{-- Name --}}
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Customer / Company Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required
                                   placeholder="e.g. John Miah"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('name')
                                <p class="text-xs text-red-600 mt-1.5 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Contact Person --}}
                        <div>
                            <label for="contact_person" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Contact Person
                            </label>
                            <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person', $customer->contact_person) }}"
                                   placeholder="Full name"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition {{ $errors->has('contact_person') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('contact_person')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label for="phone" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Phone Number
                            </label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $customer->phone) }}"
                                   placeholder="+880..."
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition {{ $errors->has('phone') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('phone')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="sm:col-span-2">
                            <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Email Address
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}"
                                   placeholder="customer@example.com"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('email')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Address --}}
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Address
                            </label>
                            <textarea name="address" id="address" rows="3"
                                      placeholder="Full address..."
                                      class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition resize-none {{ $errors->has('address') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">{{ old('address', $customer->address) }}</textarea>
                            @error('address')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Change Password Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">Change Password</h2>
                            <p class="text-xs text-gray-400">Leave blank to keep current password</p>
                        </div>
                    </div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                        {{-- Password --}}
                        <div class="relative">
                            <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                New Password
                            </label>
                            <input type="password" name="password" id="password"
                                   placeholder="Min. 8 characters"
                                   class="w-full px-4 py-2.5 pr-10 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            <button type="button" onclick="togglePassword('password', this)"
                                    class="absolute right-3 top-9 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            @error('password')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div class="relative">
                            <label for="password_confirmation" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Confirm New Password
                            </label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   placeholder="Re-enter new password"
                                   class="w-full px-4 py-2.5 pr-10 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                            <button type="button" onclick="togglePassword('password_confirmation', this)"
                                    class="absolute right-3 top-9 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Activity Log Card --}}
                @php $logs = $customer->customerLogs()->latest()->get(); @endphp
                @if($logs->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-gray-700">Activity Log</h2>
                        <span class="ml-auto text-xs text-gray-400 font-medium">{{ $logs->count() }} {{ Str::plural('entry', $logs->count()) }}</span>
                    </div>
                    <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                        @foreach($logs as $log)
                            <div class="px-6 py-4 hover:bg-gray-50 transition">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5
                                            {{ $log->action === 'created' ? 'bg-green-100 text-green-600' : ($log->action === 'updated' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500') }}">
                                            @if($log->action === 'created')
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-700 capitalize">{{ $log->action }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                by {{ optional($log->user)->name ?? 'System' }}
                                            </p>
                                            @if($log->after)
                                                <ul class="mt-2 space-y-0.5">
                                                    @foreach($log->after as $field => $newValue)
                                                        @php $oldValue = $log->before[$field] ?? null; @endphp
                                                        @if($oldValue !== $newValue)
                                                            <li class="text-xs text-gray-500">
                                                                <span class="font-medium text-gray-600">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                                <span class="line-through text-gray-400">{{ $oldValue ?: '—' }}</span>
                                                                <span class="mx-1 text-gray-300">→</span>
                                                                <span class="font-semibold text-gray-700">{{ $newValue ?: '—' }}</span>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                        @if($log->action === 'updated' && $log->before)
                                            <form action="{{ route('role.customer.restore', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'log' => $log->id]) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Restore this version?')">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit"
                                                        class="text-xs font-medium text-amber-600 hover:text-amber-700 hover:underline cursor-pointer">
                                                    Restore
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            {{-- Right: Sidebar --}}
            <div class="space-y-5">

                {{-- Customer Profile Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-br from-amber-500 to-orange-600 px-6 py-8 text-center">
                        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl font-bold mx-auto mb-3">
                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                        </div>
                        <h3 class="text-white font-semibold text-base leading-tight">{{ $customer->name }}</h3>
                        <p class="text-amber-100 text-xs mt-1">{{ $customer->email }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Customer ID</span>
                            <span class="font-mono font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">
                                EP-CU-{{ str_pad($customer->id, 3, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Status</span>
                            @if($customer->status === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>Active
                                </span>
                            @elseif($customer->status === 'on_hold')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 inline-block"></span>On Hold
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Inactive
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Joined</span>
                            <span class="font-medium text-gray-700">{{ $customer->created_at->format('d M Y') }}</span>
                        </div>
                        @if($customer->phone)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Phone</span>
                            <span class="font-medium text-gray-700">{{ $customer->phone }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Save Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-gray-700">Update Customer</h2>
                    </div>
                    <div class="p-6 space-y-3">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition shadow-sm cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                        <a href="{{ route('role.customer.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                           class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection

@section('raw-script')
<script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.innerHTML = isText
            ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`
            : `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.228 6.228A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.512 5.569M3 3l18 18"/></svg>`;
    }
</script>
@endsection
