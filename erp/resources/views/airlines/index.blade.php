@extends('layout.app')

@section('meta-information')
    <title>Airline Management</title>
@endsection

@section('main-content')
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Airlines</h1>
                <p class="mt-1 text-sm text-gray-500">Manage airline names and active status.</p>
            </div>

            @can('create airline')
                <a href="{{ route('role.airlines.create', ['role' => $role]) }}" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add Airline
                </a>
            @endcan
        </div>

        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <form method="GET" class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700" for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ request('name') }}" placeholder="Search by name" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700" for="status">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">All</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filter</button>
                    <a href="{{ route('role.airlines.index', ['role' => $role]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">SL</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        @canany(['edit airline', 'delete airline'])
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($airlines as $key => $airline)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-600">{{ ($airlines->currentPage() - 1) * $airlines->perPage() + $key + 1 }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $airline->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if ($airline->status)
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Inactive</span>
                                @endif
                            </td>
                            @canany(['edit airline', 'delete airline'])
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        @can('edit airline')
                                            <a href="{{ route('role.airlines.edit', ['role' => $role, 'airline' => $airline->id]) }}" class="rounded-lg border border-blue-200 px-3 py-1.5 text-blue-600 hover:bg-blue-50">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endcan
                                        @can('delete airline')
                                            <form action="{{ route('role.airlines.destroy', ['role' => $role, 'airline' => $airline->id]) }}" method="POST" onsubmit="return confirm('Delete this airline?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-red-600 hover:bg-red-50">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            @endcanany
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No airlines found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">
            {{ $airlines->links() }}
        </div>
    </div>
@endsection