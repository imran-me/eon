@extends('layout.app')

@section('meta-information')
    <title>Add Agent</title>
@endsection

@section('main-content')
<div class="min-h-screen bg-gray-50 p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add New Agent</h1>
            <p class="text-sm text-gray-500 mt-0.5">Epal Group ERP &rsaquo; Agent Management &rsaquo; Add Agent</p>
        </div>
        <a href="{{ route('role.agent.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
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

    @if (session('error'))
        <div class="mb-4 flex items-center gap-3 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            {{ session('error') }}
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

    <form action="{{ route('role.agent.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
          method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: Form --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Basic Information --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                Agent / Company Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   placeholder="e.g. Global Travel Agency"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
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
                            <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person') }}"
                                   placeholder="Full name"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition {{ $errors->has('contact_person') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('contact_person')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label for="phone" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Phone Number
                            </label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                                   placeholder="+880..."
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition {{ $errors->has('phone') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                            @error('phone')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="sm:col-span-2">
                            <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Email Address
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   placeholder="agent@example.com"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
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
                                      class="w-full px-4 py-2.5 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition resize-none {{ $errors->has('address') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">{{ old('address') }}</textarea>
                            @error('address')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Save Card --}}
            <div class="space-y-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-6">

                    {{-- Preview Avatar --}}
                    <div class="bg-gradient-to-br from-violet-500 to-purple-600 px-6 py-8 text-center">
                        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-white text-2xl font-bold mx-auto mb-2">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <p class="text-white/80 text-xs">New Agent</p>
                        <p class="text-white text-sm font-semibold mt-0.5">Will be Active by default</p>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="space-y-2">
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition shadow-sm cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Agent
                            </button>
                            <a href="{{ route('role.agent.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                               class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                Cancel
                            </a>
                        </div>
                        <div class="pt-3 border-t border-gray-100">
                            <p class="text-xs text-gray-400">
                                <span class="text-red-500">*</span> Required fields must be filled before saving.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection
