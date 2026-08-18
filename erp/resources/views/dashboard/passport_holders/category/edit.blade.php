@extends('layout.app')

@section('meta-information')
    <title>Edit Passport Holder Category</title>
@endsection

@section('main-content')
    <div class="bg-white p-8 mt-1">
        <h2 class="text-2xl font-semibold mb-6 text-gray-700">Edit Passport Holder Category</h2>

        {{-- Success Message --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('role.passport-holder-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => $category->id]) }}"
            method="POST">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $category->name) }}"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Submit -->
            <div class="flex justify-between items-center">
                <a href="{{ route('role.passport-holder-category.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="text-sm text-gray-600 hover:text-gray-800">
                    ← Back to List
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                    Update
                </button>
            </div>
        </form>
    </div>
@endsection
