@extends('layout.app')

@section('meta-information')
    <title>Passport Holders List</title>
@endsection

@section('main-content')
<div class="bg-white p-6">
    <div class="flex justify-between mb-4">
        <h2 class="text-xl font-bold">All Passport Holders</h2>
        <a href="{{ route('role.passport-holder.create',['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Passport Holder</a>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    <table class="min-w-full table-auto border border-gray-300 text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">Name</th>
                <th class="border px-4 py-2">Passport No</th>
                <th class="border px-4 py-2">Nationality</th>
                <th class="border px-4 py-2">Category</th>
                <th class="border px-4 py-2">Expiry</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($passportHolders as $holder)
                <tr>
                    <td class="border px-4 py-2">{{ $loop->iteration + ($passportHolders->currentPage() - 1) * $passportHolders->perPage() }}</td>
                    <td class="border px-4 py-2">{{ $holder->name }}</td>
                    <td class="border px-4 py-2">{{ $holder->passport_no }}</td>
                    <td class="border px-4 py-2">{{ $holder->nationality }}</td>
                    <td class="border px-4 py-2">{{ $holder->category->name ?? 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $holder->expiry_date }}</td>
                    <td class="border px-4 py-2 space-x-2">
                        <a href="{{ route('role.passport-holder.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder'=>$holder->id]) }}" class="text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="border px-4 py-2 text-center">No passport holders found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $passportHolders->links() }}
    </div>
</div>
@endsection
