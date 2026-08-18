@props([
  'id' => null,
  'type' => null,
  'taskId' => null,
  'multi' => false,
  'width' => 'w-56',
  'search' => false,
  'context' => 'card',
  'panelClass' => '',
  'toggleClass' => '',
  'selected' => '',
  'align' => 'left', // left|right
])

@php
  $dropdownId = $id ?? $type.'-'.($taskId ?? 'draft').'-'.Str::random(6);
  $isModal = $context === 'modal';
@endphp

<div
  class="relative {{ $toggleClass }}"
  data-dropdown="{{ $type }}"
  data-context="{{ $context }}"
  data-dropdown-id="{{ $dropdownId }}"
  @if($taskId) data-task-id="{{ $taskId }}" @endif
  data-multi="{{ $multi ? 'true' : 'false' }}"
  style="overflow: visible;"
>
  <input type="hidden" data-role="dropdown-selected" value="{{ $selected }}">

  {{-- Toggle (customizable) --}}
  @isset($toggle)
    <div data-role="dropdown-toggle" class="inline-block w-full">
      {{ $toggle }}
    </div>
  @else
    {{-- Fallback toggle --}}
    <button type="button"
            data-role="dropdown-toggle"
            class="flex items-center gap-2 select-none w-full px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
      <span data-role="dropdown-label" class="text-sm text-gray-700">Select</span>
      <svg class="w-3.5 h-3.5 text-gray-500 ml-auto" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/>
      </svg>
    </button>
  @endisset

  {{-- Panel - Using fixed positioning for card context to escape overflow constraints --}}
  <div id="panel-{{ $dropdownId }}"
       data-role="dropdown-panel"
       class="hidden fixed {{ $width }} bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] {{ $panelClass }}"
  >
    @if($search)
      <div class="p-2 border-b border-gray-100">
        <input type="text"
               data-role="dropdown-search"
               placeholder="Search..."
               class="w-full text-xs px-2 py-1 border rounded focus:outline-none focus:border-blue-400">
      </div>
    @endif

    <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">
      {{ $slot }}
    </div>
  </div>
</div>