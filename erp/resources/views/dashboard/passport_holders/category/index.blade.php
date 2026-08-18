@extends('layout.app')

@section('meta-information')
    <title>Passport Holder Categories</title>
@endsection

@section('main-content')
    <div class="bg-white p-8 mt-1">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold text-gray-700">Passport Holder Categories</h2>
            <a href="{{ route('role.passport-holder-category.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                + Add New
            </a>
        </div>

        {{-- Success Message --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if ($categories->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full text-left border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 border-b">#</th>
                            <th class="py-2 px-4 border-b">Name
                            </th>
                            <th class="py-2 px-4 border-b">
                                Created At</th>
                            <th class="py-2 px-4 border-b">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($categories as $index => $category)
                            <tr>
                                <td class="py-2 px-4 border-b">{{ $index + 1 }}</td>
                                <td class="py-2 px-4 border-b">{{ $category->name }}</td>
                                <td class="py-2 px-4 border-b">
                                    {{ $category->created_at->format('d M Y') }}</td>
                                <td class="py-2 px-4 border-b">
                                    <a href="{{ route('role.passport-holder-category.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => $category->id]) }}"
                                        class="text-blue-600 hover:text-blue-800 mr-2">Edit</a>
                                    {{-- <form
                                        action="{{ route('role.passport-holder-category.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => $category->id]) }}"
                                        method="POST" class="inline-block"
                                        onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form> --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-sm mt-4">No passport holder categories found.</p>
        @endif
    </div>
@endsection
