@extends('layout.app')

@section('main-content')
@php
    $role = Str::slug(auth()->user()->getRoleNames()->first());
@endphp

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">

<div class="h-full flex flex-col">
    <div class="flex justify-between items-center mb-4">
        @if(isset($viewMode) && $viewMode === 'my-tasks')
            <div>
                <h1 class="text-2xl font-bold text-gray-800">My Tasks</h1>
                <p class="text-sm text-gray-500 mt-0.5">Tasks assigned to or created by you in the last 30 days.</p>
            </div>
        @else
            <div class="flex items-center gap-3">
                <label class="text-sm font-semibold text-gray-700">Board</label>

                <div class="relative">
                    <x-ui.dropdown
                        type="boards"
                        context="header"
                        width="w-64"
                        toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                        panelClass="max-h-80 overflow-hidden">
                        <x-slot:toggle>
                            <button type="button" class="flex items-center gap-2">
                                <span class="text-sm text-gray-700">{{ $board->name ?? 'Select a board' }}</span>
                                <svg class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 8l4 4 4-4"/></svg>
                            </button>
                        </x-slot:toggle>

                        @foreach($boards as $b)
                            <x-ui.dropdown-item value="{{ $b->id }}" :selected="$b->id == $board->id">
                                <a href="{{ route('role.tasks.board', ['board' => $b->id, 'role' => $role]) }}" class="block w-full text-left px-3 py-2 text-sm text-gray-700">{{ $b->name }}</a>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>
                </div>

                <div id="board-hint" class="ml-2 hidden text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded flex items-center gap-2 cursor-pointer">
                    <span>Click to select a board</span>
                    <button id="board-hint-close" class="text-blue-400 font-semibold">&times;</button>
                </div>
            </div>

            <script>
                (function(){
                    try {
                        var key = 'epal_seen_board_hint_v1';
                        var seen = localStorage.getItem(key);
                        if(!seen){
                            var hint = document.getElementById('board-hint');
                            if(hint) hint.classList.remove('hidden');
                        }
                        var close = document.getElementById('board-hint-close');
                        if(close){
                            close.addEventListener('click', function(e){
                                e.stopPropagation(); e.preventDefault();
                                localStorage.setItem(key, '1');
                                var hint = document.getElementById('board-hint'); if(hint) hint.classList.add('hidden');
                            });
                        }
                        var hintDiv = document.getElementById('board-hint');
                        if(hintDiv){
                            hintDiv.addEventListener('click', function(){
                                localStorage.setItem(key, '1');
                                hintDiv.classList.add('hidden');
                            });
                        }
                    } catch (e) { console.error(e); }
                })();
            </script>
        @endif
    </div>

    @if(isset($viewMode) && $viewMode === 'my-tasks')
    {{-- ── MY TASKS: flat cross-board list ──────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto pb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3 items-start">
        @forelse($tasks as $task)
        @php
            $selectedUsers = $task->users ?? collect();
            $count = $selectedUsers->count();
            $maxAvatars = ($count <= 3) ? $count : 2;
            $extra = ($count > 3) ? ($count - 2) : 0;
            $selectedCsv = $selectedUsers->pluck('id')->implode(',');
            $labelCount = $task->labels->count();
            $labelCsv = $task->labels->pluck('id')->implode(',');
            $taskBoardColumns = $task->board ? $task->board->columns : collect();
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all group relative"
             data-id="{{ $task->id }}" style="overflow: visible;">

            {{-- 3-Dot Menu --}}
            <div class="absolute top-2 right-2 z-10">
                <button onclick="event.stopPropagation(); toggleDropdown('task-menu-{{ $task->id }}')"
                        class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                    </svg>
                </button>
                <div id="task-menu-{{ $task->id }}" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                    <button onclick='event.stopPropagation(); openEditModal({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")->toArray()), @json($task->start_date), @json($task->due_date), @json($task->priority), {{ $task->column_id }}, @json($task->labels->pluck("id")->toArray()), {{ $task->board_id }})'
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                        </svg>
                        Edit
                    </button>
                    <button onclick="event.stopPropagation(); deleteTask({{ $task->id }})"
                            class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Delete
                    </button>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="p-3 pr-10">
                {{-- Board badge --}}
                <div class="mb-1.5">
                    <span class="inline-flex items-center gap-1 text-[10px] font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H3a1 1 0 01-1-1V4z"/>
                            <path fill-rule="evenodd" d="M2 9a1 1 0 011-1h6a1 1 0 011 1v7a1 1 0 01-1 1H3a1 1 0 01-1-1V9zm10 0a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V9z" clip-rule="evenodd"/>
                        </svg>
                        {{ $task->board->name ?? 'Unknown Board' }}
                    </span>
                </div>

                {{-- Task Title --}}
                <p class="font-medium text-gray-800 text-sm leading-snug mb-2 cursor-pointer hover:text-blue-600 transition"
                   onclick='openTaskDrawer({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")), @json($task->start_date), @json($task->due_date), @json($task->priority), @json($task->column_id), @json($task->labels->pluck("id")), @json($task->creator ? $task->creator->name : "Unknown"), {{ $task->board_id }})'>
                    {{ $task->title }}
                </p>

                {{-- Inline dropdowns --}}
                <div class="flex items-center flex-wrap gap-1 overflow-visible">

                    {{-- 1. State --}}
                    <x-ui.dropdown
                        type="state"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="false"
                        :selected="(string)$task->column_id"
                        width="w-44"
                    >
                        <x-slot:toggle>
                            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium hover:opacity-80 transition
                            @switch($task->column->position ?? 1)
                                @case(1) bg-gray-100 text-gray-600 @break
                                @case(2) bg-blue-100 text-blue-600 @break
                                @case(3) bg-amber-100 text-amber-600 @break
                                @case(4) bg-emerald-100 text-emerald-600 @break
                                @case(5) bg-red-100 text-red-600 @break
                                @default bg-gray-100 text-gray-600
                            @endswitch">
                                <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                <span data-role="dropdown-label">{{ $task->column->name ?? 'None' }}</span>
                            </button>
                        </x-slot:toggle>
                        @foreach($taskBoardColumns as $col)
                            <x-ui.dropdown-item value="{{ $col->id }}" :selected="$task->column_id == $col->id" :attrs="['data-position' => $col->pivot->position ?? 1]">
                                @switch($col->pivot->position ?? 1)
                                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                    @default <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                @endswitch
                                <span>{{ $col->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>

                    {{-- 2. Priority --}}
                    <x-ui.dropdown
                        type="priority"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="false"
                        :selected="(string)($task->priority ?? '')"
                        width="w-32"
                    >
                        <x-slot:toggle>
                            <button type="button" class="p-1.5 rounded hover:bg-gray-100 transition" title="Priority: {{ $task->priority ? ucfirst($task->priority) : 'None' }}">
                                <i id="priority-icon-{{ $task->id }}" class="fa-solid fa-signal
                                    @if($task->priority === 'high') text-red-500
                                    @elseif($task->priority === 'medium') text-yellow-500
                                    @elseif($task->priority === 'low') text-blue-500
                                    @else text-gray-400 @endif"></i>
                            </button>
                        </x-slot:toggle>
                        <x-ui.dropdown-item value="high" :selected="$task->priority==='high'">
                            <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="medium" :selected="$task->priority==='medium'">
                            <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="low" :selected="$task->priority==='low'">
                            <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item value="" :selected="!$task->priority">
                            <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                        </x-ui.dropdown-item>
                    </x-ui.dropdown>

                    {{-- 3. Start Date --}}
                    <div class="relative overflow-visible">
                        <button onclick="event.stopPropagation(); toggleDropdown('startdate-{{ $task->id }}')"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                title="Start Date">
                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                            </svg>
                            <span id="startdate-label-{{ $task->id }}">{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('M d, h:i A') : '' }}</span>
                            @if($task->start_date)
                                <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'start_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                            @endif
                        </button>
                        <div id="startdate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                            <input type="datetime-local" value="{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'start_date', this.value, null);">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Due Date --}}
                    <div class="relative overflow-visible">
                        <button onclick="event.stopPropagation(); toggleDropdown('duedate-{{ $task->id }}')"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                title="Due Date">
                            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                            </svg>
                            <span id="duedate-label-{{ $task->id }}">{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, h:i A') : '' }}</span>
                            @if($task->due_date)
                                <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'due_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                            @endif
                        </button>
                        <div id="duedate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                            <input type="datetime-local" value="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'due_date', this.value, null);">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Assignees --}}
                    <x-ui.dropdown
                        type="assignees"
                        context="card"
                        :task-id="$task->id"
                        :search="true"
                        :multi="true"
                        :selected="$selectedCsv"
                        width="w-44"
                    >
                        <x-slot:toggle>
                            <button type="button" class="cursor-pointer inline-flex items-center">
                                @if($count)
                                    <div class="flex items-center">
                                        <div class="flex -space-x-2" data-role="dropdown-label">
                                            @foreach($selectedUsers->take($maxAvatars) as $u)
                                                <div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white" title="{{ $u->name }}">
                                                    @if($u->image)
                                                        <img src="{{ asset($u->image) }}" alt="{{ $u->name }}" class="w-full h-full object-cover rounded-full">
                                                    @else
                                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        @if($extra > 0)
                                            <span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">+{{ $extra }}</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white" title="Unassigned">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                @endif
                            </button>
                        </x-slot:toggle>
                        @foreach($users as $user)
                            @php $isSelected = $selectedUsers->contains('id', $user->id); @endphp
                            <x-ui.dropdown-item value="{{ $user->id }}" :selected="$isSelected" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']" class="{{ $isSelected ? 'bg-blue-50' : '' }}">
                                <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                    @if($user->image)
                                        <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                    @else
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    @endif
                                </div>
                                <span>{{ $user->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>

                    {{-- 6. Labels --}}
                    <x-ui.dropdown
                        type="labels"
                        context="card"
                        :task-id="$task->id"
                        :multi="true"
                        :search="true"
                        :selected="$labelCsv"
                        width="w-48"
                    >
                        <x-slot:toggle>
                            <button type="button" class="cursor-pointer inline-flex items-center" title="Labels: {{ $task->labels->pluck('name')->join(', ') ?: 'None' }}">
                                @if($labelCount == 0)
                                    <div class="w-5 h-5 rounded bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white">
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                        </svg>
                                    </div>
                                @elseif($labelCount <= 3)
                                    <div class="flex flex-wrap gap-1" data-role="dropdown-label">
                                        @foreach($task->labels as $label)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $label->color_details['badge'] }} {{ $label->color_details['text'] }}" style="background: {{ $label->color_details['gradient'] }}">
                                                {{ Str::limit($label->name, 12) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 text-[11px] text-gray-600" data-role="dropdown-label">
                                        <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                        </svg>
                                        <span>{{ $labelCount }} Labels</span>
                                    </div>
                                @endif
                            </button>
                        </x-slot:toggle>
                        @if(isset($labels) && $labels->count() > 0)
                            @foreach($labels as $label)
                                <x-ui.dropdown-item value="{{ $label->id }}" :selected="$task->labels?->contains($label->id)" class="{{ $task->labels->contains($label->id) ? 'bg-blue-50' : '' }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                    <span class="w-3 h-3 rounded-sm" style="background: {{ $label->color_details['gradient'] ?? '#999' }}"></span>
                                    <span class="flex-1 truncate">{{ $label->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                        @else
                            <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                        @endif
                    </x-ui.dropdown>

                </div>
            </div>
        </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center h-64 text-center">
                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-500">No tasks found</h3>
                <p class="text-sm text-gray-400 mt-1">You have no tasks assigned to or created by you in the last 30 days.</p>
            </div>
        @endforelse

        </div>

        @if(method_exists($tasks, 'links') && $tasks->hasPages())
            <div class="mt-4">
                {{ $tasks->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
    @else
    {{-- ── BOARD: Kanban view ────────────────────────────────────────────── --}}
    <div class="flex gap-4 overflow-x-auto flex-1 items-start">

        @foreach($board->board_columns as $column)
        <div class="w-80 flex-shrink-0 bg-gray-100 rounded-xl p-3 shadow-sm border border-gray-200 flex flex-col" data-column-id="{{ $column->id }}" style="height: calc(100vh - 240px); overflow: visible;">
                <div class="flex justify-between items-center mb-4 px-2">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-gray-700 uppercase text-xs tracking-widest">{{ $column->column->name }}</h3>
                        <span class="bg-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full font-bold">{{ $column->column->tasks->count() }}</span>
                    </div>
                    <button onclick="openCreateModal({{ $column->column->id }})" class="text-blue-600 hover:text-blue-800 transition p-1">
                        <i class="fas fa-plus-circle fa-lg"></i>
                    </button>
                </div>
                <div class="task-list space-y-3 p-1 rounded-lg overflow-y-auto flex-1" data-column="{{ $column->column->id }}" style="scrollbar-width: thin; scrollbar-color: #cbd5e0 #f7fafc; overflow-x: visible;">
                    @foreach($column->column->tasks as $task)
                        {{-- Compact Task Card --}}
                        <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200 hover:border-blue-400 hover:shadow-md cursor-grab active:cursor-grabbing task-item transition-all group relative"
                            style="overflow: visible;"
                            data-id="{{ $task->id }}">

                            {{-- 3-Dot Menu Button (visible on hover) --}}
                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                <button onclick="event.stopPropagation(); toggleDropdown('task-menu-{{ $task->id }}')"
                                        class="p-1 rounded hover:bg-gray-100 text-gray-600">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                                    </svg>
                                </button>

                                {{-- Dropdown Menu --}}
                                <div id="task-menu-{{ $task->id }}" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                                    <button onclick='event.stopPropagation(); openEditModal({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")->toArray()), @json($task->start_date), @json($task->due_date), @json($task->priority), {{ $task->column_id }}, @json($task->labels->pluck("id")->toArray()))'
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="event.stopPropagation(); copyTask({{ $task->id }})" 
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M4 3a1 1 0 00-1 1v11a1 1 0 001 1h5v2H4a3 3 0 01-3-3V4a3 3 0 013-3h11a3 3 0 013 3v5h-2V4a1 1 0 00-1-1H4z"/>
                                            <path d="M9 9a1 1 0 011-1h5.586l-2.293-2.293a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L15.586 10H10a1 1 0 01-1-1z"/>
                                        </svg>
                                        Duplicate
                                    </button>
                                    <button onclick="event.stopPropagation(); deleteTask({{ $task->id }})" 
                                            class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>

                            {{-- Task Title --}}
                            <p class="font-medium text-gray-800 text-sm leading-snug mb-2 cursor-pointer hover:text-blue-600 transition pr-6"
                            onclick='openTaskDrawer({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")), @json($task->start_date), @json($task->due_date), @json($task->priority), @json($task->column_id), @json($task->labels->pluck("id")), @json($task->creator ? $task->creator->name : "Unknown"), null, null)'>
                                {{ $task->title }}
                            </p>

                            {{-- Single Row Footer - All 6 Dropdowns as Icons --}}
                            <div class="mt-2 flex items-center justify-between gap-2 overflow-visible">
                                {{-- items --}}
                                <div class="flex items-center flex-wrap gap-1 overflow-visible flex-nowrap">
                                    {{-- 1. State Badge --}}
                                    <x-ui.dropdown
                                        type="state"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="false"
                                        :selected="(string)$task->column_id"
                                        width="w-44"
                                        >
                                        <x-slot:toggle>
                                            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium hover:opacity-80 transition
                                            @switch($task->column->position ?? 1)
                                                @case(1) bg-gray-100 text-gray-600 @break
                                                @case(2) bg-blue-100 text-blue-600 @break
                                                @case(3) bg-amber-100 text-amber-600 @break
                                                @case(4) bg-emerald-100 text-emerald-600 @break
                                                @case(5) bg-red-100 text-red-600 @break
                                                @default bg-gray-100 text-gray-600
                                            @endswitch
                                            ">
                                            <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                            <span data-role="dropdown-label">{{ $task->column->name }}</span>
                                            </button>
                                        </x-slot:toggle>

                                        @foreach($board->columns as $col)
                                            <x-ui.dropdown-item value="{{ $col->id }}" :selected="$task->column_id == $col->id" :attrs="['data-position' => $col->position ?? 1]">
                                                @switch($col->position ?? 1)
                                                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                                    @default <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                                @endswitch
                                            <span>{{ $col->name }}</span>
                                            </x-ui.dropdown-item>
                                        @endforeach
                                    </x-ui.dropdown>

                                    {{-- 2. Priority Icon --}}
                                    <x-ui.dropdown
                                        type="priority"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="false"
                                        :selected="(string)($task->priority ?? '')"
                                        width="w-32"
                                        >

                                        <x-slot:toggle>
                                            <button type="button"
                                            class="p-1.5 rounded hover:bg-gray-100 transition"
                                            title="Priority: {{ $task->priority ? ucfirst($task->priority) : 'None' }}"
                                            >
                                            <i id="priority-icon-{{ $task->id }}" class="fa-solid fa-signal
                                                @if($task->priority === 'high') text-red-500
                                                @elseif($task->priority === 'medium') text-yellow-500
                                                @elseif($task->priority === 'low') text-blue-500
                                                @else text-gray-400 @endif"></i>
                                            <!-- <svg class="w-4 h-4
                                                @if($task->priority === 'high') text-red-500
                                                @elseif($task->priority === 'medium') text-amber-500
                                                @elseif($task->priority === 'low') text-emerald-500
                                                @else text-gray-400 @endif"
                                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                                            </svg> -->
                                            </button>
                                        </x-slot:toggle>

                                        <x-ui.dropdown-item value="high" :selected="$task->priority==='high'">
                                            <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="medium" :selected="$task->priority==='medium'">
                                            <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="low" :selected="$task->priority==='low'">
                                            <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="" :selected="!$task->priority">
                                            <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                                        </x-ui.dropdown-item>
                                    </x-ui.dropdown>

                                    {{-- 3. Start Date --}}
                                    <div class="relative overflow-visible">
                                        <button onclick="event.stopPropagation(); toggleDropdown('startdate-{{ $task->id }}')"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                                id="startdate-btn-{{ $task->id }}"
                                                data-dropdown-toggle="startdate-{{ $task->id }}"
                                                title="Start Date">
                                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                                            </svg>
                                            <span id="startdate-label-{{ $task->id }}">{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('M d, h:i A') : '' }}</span>
                                            @if($task->start_date)
                                            <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'start_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                                            @endif
                                        </button>
                                        <div id="startdate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                                            <input type="datetime-local" value="{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'start_date', this.value, null);">
                                            <div class="flex gap-2 mt-2">
                                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 4. Due Date --}}
                                    <div class="relative overflow-visible">
                                        <button onclick="event.stopPropagation(); toggleDropdown('duedate-{{ $task->id }}')"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                                                id="duedate-btn-{{ $task->id }}"
                                                data-dropdown-toggle="duedate-{{ $task->id }}"
                                                title="Due Date">
                                            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                                            </svg>
                                            <span id="duedate-label-{{ $task->id }}">{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, h:i A') : '' }}</span>
                                            @if($task->due_date)
                                            <span onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'due_date', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>
                                            @endif
                                        </button>
                                        <div id="duedate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                                            <input type="datetime-local" value="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'due_date', this.value, null);">
                                            <div class="flex gap-2 mt-2">
                                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 6. Assignee Avatar - Always visible on right --}}
                                    @php
                                        $selectedUsers = $task->users ?? collect();
                                        $count = $selectedUsers->count();
                                        $maxAvatars = ($count <= 3) ? $count : 2;
                                        $extra = ($count > 3) ? ($count - 2) : 0;
                                        $selectedCsv = $selectedUsers->pluck('id')->implode(',');
                                    @endphp

                                    <x-ui.dropdown
                                        type="assignees"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="true"
                                        :selected="$selectedCsv"
                                        width="w-44"
                                        >
                                        <x-slot:toggle>
                                            <button type="button" class="cursor-pointer inline-flex items-center">
                                            @if($count)
                                                <div class="flex items-center">
                                                <div class="flex -space-x-2" data-role="dropdown-label">
                                                    @foreach($selectedUsers->take($maxAvatars) as $u)
                                                    <div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white"
                                                        title="{{ $u->name }}">
                                                        @if($u->image)
                                                            <img src="{{ asset($u->image) }}" alt="{{ $u->name }}" class="w-full h-full object-cover rounded-full">
                                                        @else
                                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                                        @endif
                                                    </div>
                                                    @endforeach
                                                </div>
                                                @if($extra > 0)
                                                    <span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">
                                                    +{{ $extra }}
                                                    </span>
                                                @endif
                                                </div>
                                            @else
                                                <div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white"
                                                    title="Unassigned">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                                </svg>
                                                </div>
                                            @endif
                                            </button>
                                        </x-slot:toggle>
                                        @foreach($users as $user)
                                            @php $isSelected = $selectedUsers->contains('id', $user->id); @endphp
                                            <x-ui.dropdown-item value="{{ $user->id }}" :selected="$isSelected" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']" class="{{ $isSelected ? 'bg-blue-50' : '' }}">
                                                <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                                    @if($user->image)
                                                        <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                                    @else
                                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                                    @endif
                                                </div>
                                                <span>{{ $user->name }}</span>
                                            </x-ui.dropdown-item>
                                        @endforeach
                                    </x-ui.dropdown>

                                    {{-- 5. Labels --}}
                                    @php
                                        $labelCount = $task->labels->count();
                                        $selectedCsv = $task->labels->pluck('id')->implode(',');
                                    @endphp

                                    <x-ui.dropdown
                                        type="labels"
                                        context="card"
                                        :task-id="$task->id"
                                        :multi="true"
                                        :search="true"
                                        :selected="$selectedCsv"
                                        width="w-48"
                                        >
                                        <x-slot:toggle>
                                            <button type="button" class="cursor-pointer inline-flex items-center"
                                            title="Labels: {{ $task->labels->pluck('name')->join(', ') ?: 'None' }}">
                                            @if($labelCount == 0)
                                                <div class="w-5 h-5 rounded bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white">
                                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                                    </svg>
                                                </div>
                                            @elseif($labelCount <= 3)
                                                <div class="flex flex-wrap gap-1" data-role="dropdown-label">
                                                @foreach($task->labels as $label)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $label->color_details['badge'] }} {{ $label->color_details['text'] }}" style="background: {{ $label->color_details['gradient'] }}">
                                                    {{ Str::limit($label->name, 12) }}
                                                    </span>
                                                @endforeach
                                                </div>
                                            @else
                                                <div class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 text-[11px] text-gray-600" data-role="dropdown-label">
                                                    <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                                    </svg>
                                                    <span>{{ $labelCount }} Labels</span>
                                                </div>
                                            @endif
                                            </button>
                                        </x-slot:toggle>

                                        @if(isset($labels) && $labels->count() > 0)
                                            @foreach($labels as $label)
                                                <x-ui.dropdown-item value="{{ $label->id }}" :selected="$task->labels?->contains($label->id)" class="{{ $task->labels->contains($label->id) ? 'bg-blue-50' : '' }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                                    <span class="w-3 h-3 rounded-sm" style="background: {{ $label->color_details['gradient'] ?? '#999' }}"></span>
                                                    <span class="flex-1 truncate">{{ $label->name }}</span>
                                                </x-ui.dropdown-item>
                                            @endforeach
                                        @else
                                            <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                                        @endif

                                    </x-ui.dropdown>

                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    @endif
</div>

<div id="taskModal" class="fixed inset-0 bg-gray-900/60 hidden items-center justify-center z-[55] backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl" style="overflow: visible;">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 id="modalTitle" class="text-lg font-semibold text-gray-900">Create new work item</h2>
            <button onclick="closeTaskModal()" class="p-1 rounded-full hover:bg-gray-100 text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-5" style="max-height: calc(100vh - 200px); overflow-y: auto; overflow-x: visible;">
            <input type="hidden" id="task_id">
            <input type="hidden" id="column_id">
            <input type="hidden" id="priority">

            <input type="hidden" id="assigned_users" value="[]">
            <input type="hidden" id="label_ids" value="[]">

            {{-- Title --}}
            <div>
                <input id="title" type="text"
                       class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                       placeholder="Title">
            </div>

            {{-- Description --}}
            <div>
                <textarea id="description" rows="4"
                          placeholder="Click to add description"
                          class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
            </div>

            {{-- Properties Row --}}
            <div class="flex flex-wrap gap-3" style="overflow: visible;">
                {{-- State/Column --}}
                <x-ui.dropdown
                    type="state"
                    context="modal"
                    :multi="false"
                    :search="true"
                    width="w-64"
                    :selected="old('column_id', '')"
                    panelClass="max-h-80 overflow-hidden"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                        <span class="text-sm text-gray-700" data-role="dropdown-label">State</span>

                        <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                        </button>
                    </x-slot:toggle>

                    @if(!isset($viewMode) || $viewMode !== 'my-tasks')
                    @foreach($board->columns as $column)
                        <x-ui.dropdown-item value="{{ $column->id }}" :selected="false">
                        @switch($column->position ?? 1)
                            @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                            @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                            @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                            @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                            @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                        @endswitch
                        <span class="text-gray-700">{{ $column->name }}</span>
                        </x-ui.dropdown-item>
                    @endforeach
                    @endif
                    {{-- In my-tasks mode, items are populated dynamically by JS --}}
                    </x-ui.dropdown>

                {{-- Priority --}}
                <x-ui.dropdown
                    type="priority"
                    context="modal"
                    :multi="false"
                    :search="true"
                    width="w-64"
                    :selected="old('priority','')"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                            <span class="text-sm text-gray-700" data-role="dropdown-label">Priority</span>

                            <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </x-slot:toggle>

                    <x-ui.dropdown-item value="high" :selected="false">
                        <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="medium" :selected="false">
                        <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="low" :selected="false">
                        <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                    </x-ui.dropdown-item>

                    <x-ui.dropdown-item value="" :selected="false">
                        <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                    </x-ui.dropdown-item>

                </x-ui.dropdown>

                {{-- Assignees --}}
                <x-ui.dropdown
                    type="assignees"
                    context="modal"
                    :multi="true"
                    :search="true"
                    width="w-64"
                    :selected="''"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                            <span class="text-sm text-gray-700" data-role="dropdown-label">Assignees</span>

                            <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </x-slot:toggle>

                    @foreach($users as $user)
                        <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']">
                            <div class="w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                @if($user->image)
                                    <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                @else
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                @endif
                            </div>
                            <span class="text-gray-700">{{ $user->name }}</span>
                        </x-ui.dropdown-item>
                    @endforeach
                </x-ui.dropdown>

                {{-- Labels --}}
                <x-ui.dropdown
                    type="labels"
                    context="modal"
                    :multi="true"
                    :search="true"
                    width="w-64"
                    :selected="''"
                    toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
                    >
                    <x-slot:toggle>
                        <button type="button" class="flex items-center gap-2 w-full">
                        <span class="text-sm text-gray-700" data-role="dropdown-label">Labels</span>

                        <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                        </button>
                    </x-slot:toggle>

                    @if(isset($labels) && $labels->count() > 0)
                        @foreach($labels as $label)
                            <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                <span class="w-4 h-4 rounded-sm flex-shrink-0" style="background: {{ $label->color_details['gradient'] }}"></span>
                                <span class="text-gray-700">{{ $label->name }}</span>
                            </x-ui.dropdown-item>
                        @endforeach
                    @else
                        <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                    @endif
                </x-ui.dropdown>

                {{-- Start Date --}}
                <div class="relative">
                    <button type="button" id="modalStartDateButton" onclick="toggleDropdown('modalStartDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                        <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        <span id="modalStartDateDisplay" class="text-gray-700">Add start date</span>
                    </button>
                    <div id="modalStartDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                        <input type="datetime-local" id="start_date" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateModalDate('start_date')">
                        <div class="flex gap-2 mt-2">
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                        </div>
                    </div>
                </div>

                {{-- Due Date --}}
                <div class="relative">
                    <button type="button" id="modalDueDateButton" onclick="toggleDropdown('modalDueDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                        <svg class="w-4 h-4 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        <span id="modalDueDateDisplay" class="text-gray-700">Add due date</span>
                    </button>
                    <div id="modalDueDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                        <input type="datetime-local" id="due_date" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateModalDate('due_date')">
                        <div class="flex gap-2 mt-2">
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                            <button type="button" onclick="event.stopPropagation(); setModalQuickDate('due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="create_more" class="rounded">
                <span>Create more</span>
            </label>
            <div class="flex gap-2">
                <button onclick="closeTaskModal()" class="px-4 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-700">Discard</button>
                <button onclick="saveTask()" class="px-6 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold">Save</button>
            </div>
        </div>
    </div>
</div>

<div id="taskDrawerBackdrop" class="fixed inset-0 bg-black/30 hidden z-40" onclick="closeTaskDrawer()"></div>
<div id="taskDrawer" class="fixed top-0 right-0 h-full w-full sm:w-[720px] bg-white shadow-2xl border-l border-gray-200 hidden z-50">
  <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
    <div class="flex items-center gap-2">
      <button class="p-2 rounded hover:bg-gray-100" onclick="closeTaskDrawer()" title="Back">
        <!-- back arrow -->
        <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 111.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
        </svg>
      </button>

    </div>

    <div class="flex items-center gap-2">
      <div class="relative">
        <button class="p-2 rounded hover:bg-gray-100" title="More" onclick="event.stopPropagation(); toggleDropdown('drawer-more-menu')">
          <svg class="w-5 h-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </button>

        {{-- Dropdown Menu --}}
        <div id="drawer-more-menu" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
          <button onclick="event.stopPropagation(); copyTask(currentDrawerTaskId)" 
                  class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path d="M4 3a1 1 0 00-1 1v11a1 1 0 001 1h5v2H4a3 3 0 01-3-3V4a3 3 0 013-3h11a3 3 0 013 3v5h-2V4a1 1 0 00-1-1H4z"/>
              <path d="M9 9a1 1 0 011-1h5.586l-2.293-2.293a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L15.586 10H10a1 1 0 01-1-1z"/>
            </svg>
            Duplicate
          </button>
          <button onclick="event.stopPropagation(); deleteTask(currentDrawerTaskId)" 
                  class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Delete
          </button>
        </div>
      </div>

    </div>
  </div>

  <div class="h-[calc(100%-56px)] overflow-y-auto">
    <div class="px-6 py-6">

      <!-- Task Code -->
      <div class="text-xs text-gray-500 font-medium" id="drawerTaskCode"></div>

      <!-- Title -->
      <div class="mt-1 text-2xl font-semibold text-gray-900" id="drawerTaskTitle"></div>

      <!-- Description -->
      <div class="mt-4">
        <div class="w-full">
          <textarea
            rows="5"
            class="w-full min-w-0 resize-none border min-w-0 resize-none border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm"
            id="drawerTaskDescription"
            placeholder="Write description...">
          </textarea>
        </div>

        <!-- last edited -->
        <div class="mt-4 flex items-center justify-end text-xs text-gray-500">
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 8.414V5a1 1 0 10-2 0v6a1 1 0 00.293.707l3 3a1 1 0 001.414-1.414L11 10.414z"/>
            </svg>
            <span id="drawerLastEdited"></span>
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="mt-6 flex flex-wrap gap-2">
        {{-- Add sub-work item dropdown --}}
        <div class="relative" id="addSubworkWrapper">
            <button onclick="event.stopPropagation(); toggleDropdown('addSubworkMenu')"
                    class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zm6-8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/>
                </svg>
                Add sub-work item
                <svg class="w-3 h-3 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
            <div id="addSubworkMenu" class="hidden absolute left-0 top-full mt-1 w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                <button onclick="closeAllDropdowns(); openCreateSubtaskModal(currentDrawerTaskId)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                    Create new
                </button>
                <button onclick="closeAllDropdowns(); openAddParentModal(currentDrawerTaskId, true)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                    Add existing
                </button>
            </div>
        </div>

        <button class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2" onclick="openAddLinkModal()">
          <span class="text-gray-600">🔗</span> Add link
        </button>
        <button class="px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm flex items-center gap-2"
                onclick="document.getElementById('attachmentInput').click()">
            📎 Attach
        </button>
        <input type="file" id="attachmentInput" name="attachment" class="hidden" multiple accept="*/*" onchange="handleAttachmentUpload(event)">
      </div>

      {{-- Sub-work items section --}}
      <div id="drawerSubtasksSection" class="mt-5">
          <div class="border border-gray-200 rounded-md overflow-hidden">
              <div class="flex items-center justify-between px-3 py-2 bg-white">
                  <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                          onclick="toggleSection('drawerSubtasksBody')">
                      <svg id="drawerSubtasksCaret" class="w-4 h-4 text-gray-500 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                      </svg>
                      <span>Sub-work items</span>
                      <span id="drawerSubtasksCount" class="text-gray-500 font-normal ml-1"></span>
                  </button>
                  <div id="drawerSubtasksProgress" class="hidden items-center gap-1.5">
                      <svg class="w-4 h-4 -rotate-90" viewBox="0 0 16 16">
                          <circle cx="8" cy="8" r="6" fill="none" stroke="#e5e7eb" stroke-width="2.5"></circle>
                          <circle class="subtask-ring" cx="8" cy="8" r="6" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="37.7" stroke-dashoffset="37.7"></circle>
                      </svg>
                      <span class="subtask-pct text-xs font-medium text-gray-600"></span>
                  </div>
              </div>
              <div id="drawerSubtasksBody" class="border-t border-gray-200">
                  <div id="drawerSubtasksList" class="divide-y divide-gray-100"></div>
              </div>
          </div>
      </div>

    <div class="mt-5 space-y-4">
        <!-- LINKS -->
        <div class="border border-gray-200 rounded-md overflow-hidden">
            <!-- header -->
            <div class="flex items-center justify-between px-3 py-2 bg-white">
                <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                        onclick="toggleSection('drawerLinksBody')">
                    <!-- caret -->
                    <svg id="drawerLinksCaret" class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                    <span>Links</span>
                    <span class="text-gray-500 font-normal" id="drawerLinksCount">1</span>
                </button>

                <button class="p-1.5 rounded hover:bg-gray-100" title="Add link" onclick="openAddLinkModal()">
                    <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/>
                    </svg>
                </button>
            </div>

            <!-- body -->
            <div id="drawerLinksBody" class="border-t border-gray-200">
                <!-- list -->
                <div class="p-2 space-y-2" id="drawerLinksList">

                    <!-- single link row -->
                    <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-md border border-gray-200 hover:bg-gray-50">
                        <div class="flex items-center gap-2 min-w-0">
                            <!-- link icon -->
                            <svg class="w-4 h-4 text-gray-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M12.586 2.586a2 2 0 012.828 0l2 2a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0 1 1 0 011.414-1.414.5.5 0 00.707 0l5-5a.5.5 0 000-.707l-2-2a.5.5 0 00-.707 0l-2 2A1 1 0 019.172 4l2.414-2.414z"/>
                            <path d="M7.414 7.414a2 2 0 012.828 0 1 1 0 11-1.414 1.414.5.5 0 00-.707 0l-5 5a.5.5 0 000 .707l2 2a.5.5 0 00.707 0l2-2A1 1 0 019.828 16l-2.414 2.414a2 2 0 01-2.828 0l-2-2a2 2 0 010-2.828l5-5z"/>
                            </svg>

                            <a href="#"
                            class="text-sm text-gray-800 hover:text-blue-600 truncate"
                            title="epal it solutions">
                            epal it solutions
                            </a>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-500">4 minutes ago</span>

                            <!-- copy -->
                            <button class="p-1.5 rounded hover:bg-gray-100" title="Copy link">
                                <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 7a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H9a2 2 0 01-2-2V7z"/>
                                    <path d="M5 3a2 2 0 00-2 2v8a2 2 0 002 2h1a1 1 0 100-2H5V5h8v1a1 1 0 102 0V5a2 2 0 00-2-2H5z"/>
                                </svg>
                            </button>

                            <!-- menu -->
                            <button class="p-1.5 rounded hover:bg-gray-100" title="More">
                                <svg class="w-5 h-5 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ATTACHMENTS -->
            <div class="border border-gray-200 rounded-md overflow-hidden">
                <!-- header -->
                <div class="flex items-center justify-between px-3 py-2 bg-white">
                    <button class="flex items-center gap-2 text-sm font-medium text-gray-800"
                            onclick="toggleSection('drawerAttachmentsBody')">
                        <svg id="drawerAttachCaret" class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                        <span>Attachments</span>
                        <span class="text-gray-500 font-normal" id="drawerAttachmentsCount">2</span>
                    </button>

                    <button class="p-1.5 rounded hover:bg-gray-100" title="Add attachment" onclick="document.getElementById('attachmentInput').click()">
                        <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/>
                        </svg>
                    </button>
                </div>

                <!-- body -->
                <div id="drawerAttachmentsBody" class="border-t border-gray-200">
                    <div class="p-2 space-y-2" id="drawerAttachmentsList">
                        {{-- dynamic attachment image will append here --}}
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mt-8 border-b border-gray-200">
                <div class="flex gap-6">
                <button id="tabPropertiesBtn"
                        class="py-3 text-sm font-medium border-b-2 border-blue-600 text-blue-600"
                        onclick="showDrawerTab('properties')">
                    Properties
                </button>
                <button id="tabActivityBtn"
                        class="py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-800"
                        onclick="showDrawerTab('activity')">
                    Activity
                </button>
                </div>
            </div>

            <!-- Properties -->
            <div id="tabProperties" class="mt-5">
                <div class="grid grid-cols-1 gap-4 pb-96">

                {{-- Assignees --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>👥</span> Assignees
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="assignees"
                            context="drawer"
                            :multi="true"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select assignees</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @foreach($users as $user)
                                <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image? asset($user->image) : '']">
                                    <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-[10px] text-white font-semibold">
                                        @if($user->image)
                                            <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                        @else
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <span class="text-gray-700">{{ $user->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                        </x-ui.dropdown>
                    </div>
                </div>

                {{-- State/Column --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>◯</span> State
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="state"
                            context="drawer"
                            :multi="false"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select state</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @if(!isset($viewMode) || $viewMode !== 'my-tasks')
                            @foreach($board->columns as $col)
                                <x-ui.dropdown-item value="{{ $col->id }}" :attrs="['data-name' => $col->name]">
                                    @switch($col->position ?? 1)
                                        @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                        @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                        @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                        @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                        @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                    @endswitch
                                    <span class="text-gray-700">{{ $col->name }}</span>
                                </x-ui.dropdown-item>
                            @endforeach
                            @endif
                            {{-- In my-tasks mode, items are populated dynamically by JS --}}
                        </x-ui.dropdown>
                    </div>
                </div>


                {{-- Priority --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>📶</span> Priority
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="priority"
                            context="drawer"
                            :multi="false"
                            :search="false"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">None</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            <x-ui.dropdown-item value="high">
                                <i class="fa-solid fa-signal text-red-500"></i>
                                <span class="text-gray-700">High</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="medium">
                                <i class="fa-solid fa-signal text-yellow-500"></i>
                                <span class="text-gray-700">Medium</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="low">
                                <i class="fa-solid fa-signal text-blue-500"></i>
                                <span class="text-gray-700">Low</span>
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item value="">
                                <i class="fa-solid fa-minus text-gray-400"></i>
                                <span class="text-gray-700">None</span>
                            </x-ui.dropdown-item>
                        </x-ui.dropdown>
                    </div>
                </div>

                <!-- Row: Parent -->
                <div id="drawerParentRow" class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zm6-8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zm0 8a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/></svg>
                        Parent
                    </div>
                    <div class="col-span-8">
                        <div id="drawerParentValue" class="px-3 py-2 text-sm text-gray-800">
                            {{-- Populated by JS --}}
                        </div>
                    </div>
                </div>

                <!-- Row: Created by -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>🧑</span> Created by
                    </div>
                    <div class="col-span-8">
                        <div class="px-3 py-2 text-sm text-gray-800">
                            <span id="drawerCreatedBy">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Row: Start date -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                    <span>📅</span> Start date
                    </div>
                    <div class="col-span-8 relative">
                        <button onclick="toggleDropdown('drawerStartDatePicker')" class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm" id="drawerStartDateButton">
                            <span id="drawerStartDateDisplay">Add start date</span>
                        </button>
                        <div id="drawerStartDatePicker" class="drawer-dropdown hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start  Date</label>
                            <input type="datetime-local" id="drawerStartDateInput" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateDrawerDate(currentDrawerTaskId, 'start_date')">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Due date -->
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                    <span>🗓️</span> Due date
                    </div>
                    <div class="col-span-8 relative">
                        <button onclick="toggleDropdown('drawerDueDatePicker')" class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm" id="drawerDueDateButton">
                            <span id="drawerDueDateDisplay">Add due date</span>
                        </button>
                        <div id="drawerDueDatePicker" class="drawer-dropdown hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                            <input type="datetime-local" id="drawerDueDateInput" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateDrawerDate(currentDrawerTaskId, 'due_date')">
                            <div class="flex gap-2 mt-2">
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                <button onclick="event.stopPropagation(); setDrawerQuickDate(currentDrawerTaskId, 'due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row: Modules -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>▦</span> Modules
                    </div>
                    <div class="col-span-8">
                        <div class="px-3 py-2 text-sm text-gray-500" id="drawerModules">No module</div>
                    </div>
                </div> --}}

                <!-- Row: Cycle -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                        <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                            <span>⟳</span> Cycle
                        </div>
                        <div class="col-span-8">
                            <div class="px-3 py-2 text-sm text-gray-500" id="drawerCycle">No cycle</div>
                        </div>
                </div> --}}

                <!-- Row: Parent -->
                {{-- <div class="grid grid-cols-12 items-center gap-3">
                        <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                            <span>↥</span> Parent
                        </div>
                        <div class="col-span-8">
                            <button class="w-full text-left px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm">
                                <span class="text-gray-500" id="drawerParent">Add parent work item</span>
                            </button>
                        </div>
                    </div> --}}

                <!-- Row: Labels -->
                {{-- Labels --}}
                <div class="grid grid-cols-12 items-center gap-3">
                    <div class="col-span-4 text-sm text-gray-600 flex items-center gap-2">
                        <span>🏷️</span> Labels
                    </div>

                    <div class="col-span-8">
                        <x-ui.dropdown
                            type="labels"
                            context="drawer"
                            :multi="true"
                            :search="true"
                            width="w-64"
                            :selected="''"
                            toggleClass="w-full flex items-center gap-2 px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                        >
                            <x-slot:toggle>
                                <button type="button" class="w-full flex items-center gap-2">
                                    <span class="text-gray-700" data-role="dropdown-label">Select labels</span>
                                    <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </x-slot:toggle>

                            @if(isset($labels) && $labels->count() > 0)
                                @foreach($labels as $label)
                                    <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                        <span class="w-3.5 h-3.5 rounded-sm" style="background: {{ $label->color_details['gradient'] }}"></span>
                                        <span class="text-gray-700">{{ $label->name }}</span>
                                    </x-ui.dropdown-item>
                                @endforeach
                            @else
                                <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                            @endif
                        </x-ui.dropdown>
                    </div>
                </div>

                </div>
            </div>

            <!-- Activity -->
            <div id="tabActivity" class="mt-5 hidden">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                    </svg>
                    <div class="text-lg font-semibold text-gray-900">Feedback &amp; Communication</div>
                </div>

                <!-- Timeline list -->
                <div class="mt-4 space-y-4" id="drawerActivityList">
                    <div class="text-center py-8 text-gray-400 text-sm">
                        Loading activities...
                    </div>
                </div>

                <!-- Comment box -->
                <div class="mt-6">
                    <label for="drawerCommentInput" class="block text-sm font-medium text-gray-700 mb-2">
                        Add a comment (as {{ ucfirst($role) }})
                    </label>
                    <textarea id="drawerCommentInput"
                            class="w-full border border-gray-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-200 focus:border-purple-300 resize-none"
                            rows="3"
                            placeholder="e.g. I reviewed this task — please prioritise the marketing proposal instead."></textarea>
                    <div class="mt-2 flex justify-end">
                        <button onclick="submitComment()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                            Comment
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </div>

{{-- ───────────── Create sub-task modal ───────────── --}}
<div id="createSubtaskBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeCreateSubtaskModal()"></div>
<div id="createSubtaskModal" class="fixed inset-0 hidden z-[55] items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl" style="overflow: visible;">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-900">Create sub-work item</h3>
      <button onclick="closeCreateSubtaskModal()" class="p-1 rounded-full hover:bg-gray-100 text-gray-500">
        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>

    <div class="px-6 py-5 space-y-5" style="max-height: calc(100vh - 200px); overflow-y: auto; overflow-x: visible;">
      <input type="hidden" id="createSubtaskParentId">
      <input type="hidden" id="createSubtaskColumnId">
      <input type="hidden" id="createSubtaskPriority">
      <input type="hidden" id="createSubtaskAssignedUsers" value="[]">
      <input type="hidden" id="createSubtaskLabelIds" value="[]">

      {{-- Title --}}
      <div>
        <input id="createSubtaskTitle" type="text" placeholder="Sub-task title"
               class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
      </div>

      {{-- Description --}}
      <div>
        <textarea id="createSubtaskDescription" rows="4"
                  placeholder="Click to add description"
                  class="w-full border border-gray-300 px-3 py-2.5 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"></textarea>
      </div>

      {{-- Properties Row --}}
      <div class="flex flex-wrap gap-3" style="overflow: visible;">
        {{-- State/Column --}}
        <x-ui.dropdown
            type="state"
            context="subtask"
            :multi="false"
            :search="true"
            width="w-64"
            :selected="''"
            panelClass="max-h-80 overflow-hidden"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                <span class="text-sm text-gray-700" data-role="dropdown-label">State</span>
                <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                </button>
            </x-slot:toggle>

            @if(isset($board))
            @foreach($board->columns as $column)
                <x-ui.dropdown-item value="{{ $column->id }}" :selected="false">
                @switch($column->position ?? 1)
                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                @endswitch
                <span class="text-gray-700">{{ $column->name }}</span>
                </x-ui.dropdown-item>
            @endforeach
            @endif
            </x-ui.dropdown>

        {{-- Priority --}}
        <x-ui.dropdown
            type="priority"
            context="subtask"
            :multi="false"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                    <span class="text-sm text-gray-700" data-role="dropdown-label">Priority</span>
                    <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </x-slot:toggle>

            <x-ui.dropdown-item value="high" :selected="false">
                <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="medium" :selected="false">
                <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="low" :selected="false">
                <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
            </x-ui.dropdown-item>
            <x-ui.dropdown-item value="" :selected="false">
                <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
            </x-ui.dropdown-item>
        </x-ui.dropdown>

        {{-- Assignees --}}
        <x-ui.dropdown
            type="assignees"
            context="subtask"
            :multi="true"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                    <span class="text-sm text-gray-700" data-role="dropdown-label">Assignees</span>
                    <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </x-slot:toggle>

            @if(isset($users))
                @foreach($users as $user)
                    <x-ui.dropdown-item value="{{ $user->id }}" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']">
                        <div class="w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                            @if($user->image)
                                <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <span class="text-gray-700">{{ $user->name }}</span>
                    </x-ui.dropdown-item>
                @endforeach
            @endif
        </x-ui.dropdown>

        {{-- Labels --}}
        <x-ui.dropdown
            type="labels"
            context="subtask"
            :multi="true"
            :search="true"
            width="w-64"
            :selected="''"
            toggleClass="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
            >
            <x-slot:toggle>
                <button type="button" class="flex items-center gap-2 w-full">
                <span class="text-sm text-gray-700" data-role="dropdown-label">Labels</span>
                <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                </button>
            </x-slot:toggle>

            @if(isset($labels) && $labels->count() > 0)
                @foreach($labels as $label)
                    <x-ui.dropdown-item value="{{ $label->id }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                        <span class="w-4 h-4 rounded-sm flex-shrink-0" style="background: {{ $label->color_details['gradient'] }}"></span>
                        <span class="text-gray-700">{{ $label->name }}</span>
                    </x-ui.dropdown-item>
                @endforeach
            @else
                <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
            @endif
        </x-ui.dropdown>

        {{-- Start Date --}}
        <div class="relative">
            <button type="button" id="createSubtaskStartDateButton" onclick="toggleDropdown('createSubtaskStartDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                <span id="createSubtaskStartDateDisplay" class="text-gray-700">Add start date</span>
            </button>
            <div id="createSubtaskStartDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                <input type="datetime-local" id="createSubtaskStartDate" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateSubtaskDate('start_date')">
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('start_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                </div>
            </div>
        </div>

        {{-- Due Date --}}
        <div class="relative">
            <button type="button" id="createSubtaskDueDateButton" onclick="toggleDropdown('createSubtaskDueDatePicker')" class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-sm">
                <svg class="w-4 h-4 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                <span id="createSubtaskDueDateDisplay" class="text-gray-700">Add due date</span>
            </button>
            <div id="createSubtaskDueDatePicker" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-3" style="width: 16rem;">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                <input type="datetime-local" id="createSubtaskDueDate" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onchange="updateSubtaskDate('due_date')">
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', 0)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', 1)" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                    <button type="button" onclick="event.stopPropagation(); setSubtaskQuickDate('due_date', null)" class="flex-1 text-[10px] px-2 py-1 bg-red-100 hover:bg-red-200 rounded text-red-600">Clear</button>
                </div>
            </div>
        </div>
      </div>
    </div>

    {{-- Footer --}}
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" id="createSubtaskCreateMore" class="rounded">
            <span>Create more</span>
        </label>
        <div class="flex gap-2">
            <button onclick="closeCreateSubtaskModal()" class="px-4 py-2 text-sm rounded-md border border-gray-300 hover:bg-gray-50 text-gray-700">Discard</button>
            <button onclick="submitCreateSubtask()" class="px-6 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold">Create</button>
        </div>
    </div>
  </div>
</div>

{{-- ───────────── Add existing / set parent modal ───────────── --}}
<div id="addParentBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeAddParentModal()"></div>
<div id="addParentModal" class="fixed inset-0 hidden z-50 items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="w-full max-w-lg bg-white rounded-lg shadow-xl border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-900" id="addParentModalTitle">Add existing work item</h3>
      <button onclick="closeAddParentModal()" class="p-1 rounded hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>
    <div class="px-6 py-4">
      <input type="hidden" id="addParentTaskId">
      <input type="hidden" id="addParentMode"> {{-- "subtask" or "parent" --}}
      <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
        <input type="text" id="addParentSearchInput" placeholder="Type to search"
               class="w-full border border-gray-200 rounded-md pl-9 pr-3 py-2 text-sm focus:outline-none focus:border-blue-400"
               oninput="searchParentTasks(this.value)">
      </div>
      <div class="flex items-center justify-between mb-2">
        <span id="addParentSelectedCount" class="text-xs text-gray-500">No work items selected</span>
        <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
          <span>Workspace level</span>
          <span class="relative inline-block w-9 h-5">
            <input type="checkbox" id="addParentWorkspaceLevel" class="peer sr-only" onchange="searchParentTasks(document.getElementById('addParentSearchInput').value)">
            <span class="absolute inset-0 rounded-full bg-gray-300 peer-checked:bg-blue-600 transition-colors"></span>
            <span class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:translate-x-4"></span>
          </span>
        </label>
      </div>
      <div id="addParentSearchResults" class="max-h-60 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-md">
        <p class="px-3 py-4 text-sm text-gray-400 text-center">Type to search tasks...</p>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
      <button onclick="closeAddParentModal()" class="px-4 py-2 text-sm text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50">Cancel</button>
      <button id="addSelectedWorkItemsBtn" onclick="addSelectedWorkItems()" disabled
              class="hidden px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
        Add selected work items
      </button>
    </div>
  </div>
</div>

<div id="addLinkBackdrop" class="fixed inset-0 bg-black/30 hidden z-50" onclick="closeAddLinkModal()"></div>
<div id="addLinkModal" class="fixed inset-0 hidden z-50 flex items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="w-full max-w-2xl bg-white rounded-lg shadow-xl border border-gray-200">
    <!-- Header -->
    <div class="px-6 py-4">
      <h3 class="text-lg font-semibold text-gray-900">Add link</h3>
    </div>

    <!-- Body -->
    <div class="px-6 pb-5 space-y-4">
      <!-- URL -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
        <input id="linkUrl"
               type="url"
               placeholder="Type or paste a URL"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
        <p id="linkUrlError" class="mt-1 text-xs text-red-600 hidden">Please enter a valid URL.</p>
      </div>

      <!-- Display title -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Display title</label>
        <div class="text-xs text-gray-500 -mt-1 mb-1">Optional</div>
        <input id="linkTitle" type="text" placeholder="What you'd like to see this link as"
        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
      </div>

      <!-- Hidden: store task id -->
      <input type="hidden" id="linkTaskId" value="">
    </div>

    <!-- Footer -->
    <div class="px-6 py-4 flex items-center justify-end gap-2 border-t border-gray-200">
        <button type="button" class="px-4 py-2 text-sm rounded border border-gray-300 hover:bg-gray-50" onclick="closeAddLinkModal()">
            Cancel
        </button>

        <button type="button" class="px-4 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700" id="linkbutton" onclick="submitAddLink()">
            Add Link
        </button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    // ── Server-side config passed to JS modules ──
    window.__boardConfig = {
        role: @json($role),
        @if(isset($viewMode) && $viewMode === 'my-tasks')
        boardId: null,
        workspaceId: null,
        projectId: null,
        viewMode: 'my-tasks',
        boardColumnsMap: @json($boardColumnsMap),
        columns: [],
        @else
        boardId: {{ $board->id }},
        workspaceId: {{ $board->workspace_id }},
        projectId: {{ $board->project_id }},
        viewMode: 'board',
        boardColumnsMap: {},
        columns: @json($board->columns->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'position' => $c->position ?? 1])->values()),
        @endif
        users: @json(($users ?? collect())->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'image' => $u->image ? asset($u->image) : null])->values()),
        moveUrl: "{{ route('role.tasks.move', $role) }}",
        attachmentUploadUrl: "{{ route('role.tasks.attachments', $role) }}",
        linkStoreUrl: "{{ route('role.tasks.links.store', $role) }}",
    };
</script>
{{-- "My Tasks" state-dropdown-per-board logic lives in resources/js/pages/board-show.js
     (runs at runtime when window.__boardConfig.viewMode === 'my-tasks'). --}}

@endsection
