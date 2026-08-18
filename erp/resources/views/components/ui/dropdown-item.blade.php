@props([
  'value' => null,
  'selected' => false,
  'class' => '',
  'attrs' => [],
])

<div
  data-role="dropdown-item"
  data-value="{{ $value }}"
  @foreach($attrs as $key => $val) {{ $key }}="{{ $val }}" @endforeach
  class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-xs {{ $selected ? 'bg-gray-100' : '' }} {{ $class }}"
>
  {{ $slot }}

  <svg data-role="dropdown-check"
       class="w-3 h-3 ml-auto text-gray-400 {{ $selected ? '' : 'hidden' }}"
       fill="currentColor" viewBox="0 0 20 20">
    <path fill-rule="evenodd"
          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
          clip-rule="evenodd"/>
  </svg>
</div>