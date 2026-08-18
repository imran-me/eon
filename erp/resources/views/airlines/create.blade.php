@extends('layout.app')

@section('meta-information')
    <title>Create Airline</title>
@endsection

@section('main-content')
    <div class="mx-auto max-w-2xl rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 px-6 py-4">
            <h1 class="text-xl font-semibold text-gray-900">Create Airline</h1>
            <p class="mt-1 text-sm text-gray-500">Add a new airline master record.</p>
        </div>

        <form action="{{ route('role.airlines.store', ['role' => $role]) }}" method="POST" class="px-6 py-6">
            @csrf
            @include('airlines._form', ['airline' => null])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Save Airline
                </button>
                <a href="{{ route('role.airlines.index', ['role' => $role]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection