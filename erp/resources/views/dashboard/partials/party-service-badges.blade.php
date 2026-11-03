@php
    $serviceMeta = [
        'ticket' => ['Ticket', 'fa-ticket-alt', 'bg-indigo-50 text-indigo-700'],
        'visa' => ['Visa', 'fa-passport', 'bg-blue-50 text-blue-700'],
        'flight' => ['Flight', 'fa-plane', 'bg-sky-50 text-sky-700'],
        'file' => ['File', 'fa-folder-open', 'bg-orange-50 text-orange-700'],
        'instructor' => ['Instructor', 'fa-chalkboard-teacher', 'bg-emerald-50 text-emerald-700'],
        'others' => ['Others', 'fa-ellipsis-h', 'bg-gray-100 text-gray-700'],
    ];
@endphp
<div class="flex flex-wrap gap-1">
    @forelse($party->service_categories ?? [] as $service)
        @if(isset($serviceMeta[$service]))
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-semibold {{ $serviceMeta[$service][2] }}">
                <i class="fas {{ $serviceMeta[$service][1] }}"></i>{{ $serviceMeta[$service][0] }}
            </span>
        @endif
    @empty
        <span class="text-xs italic text-gray-300">Not set</span>
    @endforelse
</div>
