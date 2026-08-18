@extends('layout.app')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp
@section('meta-information')
    <title>Trash — Deleted Records</title>
@endsection
@section('css')
@include('layout.table-design')
<style>
    .module-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #e0e7ff;
        color: #3730a3;
    }
    .trash-group-header {
        background: #f1f5f9;
        border-left: 4px solid #3b82f6;
        padding: 10px 16px;
        font-weight: 600;
        color: #1e3a8a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .trash-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: opacity .2s;
    }
    .trash-action-btn:hover { opacity: .85; }
    .btn-restore  { background: #22c55e; color: #fff; }
    .btn-destroy  { background: #ef4444; color: #fff; }
    .btn-restore-all { background: #3b82f6; color: #fff; }
    .btn-empty    { background: #7f1d1d; color: #fff; }
    .empty-trash  { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .module-filter-bar { display: flex; flex-wrap: wrap; gap: 8px; padding: 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .filter-pill {
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        color: #475569;
        background: #fff;
        transition: all .2s;
    }
    .filter-pill:hover, .filter-pill.active { background: #1e40af; color: #fff; border-color: #1e40af; }
</style>
@endsection

@section('main-content')
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-red-700 px-6 py-4 flex justify-between items-center">
            <h2 class="text-white text-xl font-semibold" style="color:white">
                <i class="fas fa-trash-alt mr-2"></i>Trash
                @if($totalCount > 0)
                    <span class="ml-2 bg-white text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $totalCount }}</span>
                @endif
            </h2>
            <span class="text-red-200 text-sm" style="color: #fecaca">
                Deleted items are kept here. You can restore or permanently delete them.
            </span>
        </div>

        {{-- Module filter pills --}}
        <div class="module-filter-bar">
            <a href="{{ route('role.trash.index', ['role' => $role]) }}" class="filter-pill {{ $filter === 'all' ? 'active' : '' }}">
                <i class="fas fa-layer-group mr-1"></i>All Modules
            </a>
            @foreach($trashables as $key => $config)
                @can($config['permission'])
                <a href="{{ route('role.trash.index', ['role' => $role, 'module' => $key]) }}"
                   class="filter-pill {{ $filter === $key ? 'active' : '' }}">
                    <i class="fas {{ $config['icon'] }} mr-1"></i>{{ $config['label'] }}
                </a>
                @endcan
            @endforeach
        </div>

        {{-- Content --}}
        <div class="states-table-content p-4">
            @if($totalCount === 0)
                <div class="empty-trash">
                    <i class="fas fa-check-circle fa-3x mb-3" style="color: #22c55e"></i>
                    <p class="text-lg font-semibold mt-2">Trash is empty</p>
                    <p class="text-sm mt-1">No deleted records found{{ $filter !== 'all' ? ' for this module' : '' }}.</p>
                </div>
            @else
                @foreach($groups as $module => $group)
                <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                    {{-- Group header --}}
                    <div class="trash-group-header">
                        <span>
                            <i class="fas {{ $group['icon'] }} mr-2"></i>
                            {{ $group['label'] }}
                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ count($group['records']) }}
                            </span>
                        </span>
                        <div class="flex gap-2">
                            {{-- Restore All --}}
                            <form method="POST" action="{{ route('role.trash.restore-all', ['role' => $role, 'module' => $module]) }}"
                                  onsubmit="return confirm('Restore all {{ $group['label'] }} records?')">
                                @csrf @method('PUT')
                                <button type="submit" class="trash-action-btn btn-restore-all">
                                    <i class="fas fa-undo"></i> Restore All
                                </button>
                            </form>
                            {{-- Empty module --}}
                            <form method="POST" action="{{ route('role.trash.empty-module', ['role' => $role, 'module' => $module]) }}"
                                  onsubmit="return confirm('Permanently delete ALL {{ $group['label'] }} records? This cannot be undone!')">
                                @csrf @method('DELETE')
                                <button type="submit" class="trash-action-btn btn-empty">
                                    <i class="fas fa-fire"></i> Empty
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Records table --}}
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-2 text-left">#</th>
                                <th class="px-4 py-2 text-left">Record</th>
                                <th class="px-4 py-2 text-left">Deleted At</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($group['records'] as $i => $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-500">{{ $record['id'] }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ $record['title'] }}
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $record['deleted_at']->format('d M Y, h:i A') }}
                                    <span class="text-xs text-gray-400 ml-1">({{ $record['deleted_at']->diffForHumans() }})</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex justify-end gap-2">
                                        {{-- Restore --}}
                                        <form method="POST"
                                              action="{{ route('role.trash.restore', ['role' => $role, 'module' => $record['module'], 'id' => $record['id']]) }}">
                                            @csrf @method('PUT')
                                            <button type="submit" class="trash-action-btn btn-restore"
                                                    title="Restore">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </form>
                                        {{-- Permanent delete --}}
                                        <form method="POST"
                                              action="{{ route('role.trash.force-delete', ['role' => $role, 'module' => $record['module'], 'id' => $record['id']]) }}"
                                              onsubmit="return confirm('Permanently delete this record? This cannot be undone!')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="trash-action-btn btn-destroy"
                                                    title="Delete Forever">
                                                <i class="fas fa-times"></i> Delete Forever
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            @endif
        </div>

    </div>
</div>
@endsection
