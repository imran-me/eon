@props(['task', 'board', 'users', 'labels'])

<div class="bg-white rounded-lg border border-gray-200 p-3 space-y-2"
     data-task-id="{{ $task->id }}">

  <div class="flex items-start justify-between gap-2">
    <div class="font-medium text-sm text-gray-900" data-role="task-title">
      {{ $task->title }}
    </div>

    {{-- your menu button --}}
    <button type="button"
            class="text-gray-500 hover:text-gray-700"
            data-action="task-menu"
            data-task-id="{{ $task->id }}">
      ⋮
    </button>
  </div>

  <div class="flex flex-wrap gap-2 items-center">
    {{-- State dropdown --}}
    <x-ui.dropdown
      context="card"
      type="state"
      :task-id="$task->id"
      :multi="false"
      :search="true"
      :label="$task->column->name ?? 'State'"
      toggleClass="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
    >
      @foreach($board->columns as $column)
        <x-ui.dropdown-item value="{{ $column->id }}" :selected="$task->column_id === $column->id">
          {{ $column->name }}
        </x-ui.dropdown-item>
      @endforeach
    </x-ui.dropdown>

    {{-- Priority dropdown --}}
    <x-ui.dropdown
      context="card"
      type="priority"
      :task-id="$task->id"
      :multi="false"
      :search="false"
      :label="ucfirst($task->priority ?? 'Priority')"
      toggleClass="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer"
    >
      <x-ui.dropdown-item value="" :selected="empty($task->priority)">None</x-ui.dropdown-item>
      <x-ui.dropdown-item value="low" :selected="$task->priority==='low'">Low</x-ui.dropdown-item>
      <x-ui.dropdown-item value="medium" :selected="$task->priority==='medium'">Medium</x-ui.dropdown-item>
      <x-ui.dropdown-item value="high" :selected="$task->priority==='high'">High</x-ui.dropdown-item>
    </x-ui.dropdown>

    {{-- Priority badge rendered by JS (optional separate view) --}}
    <span data-role="task-priority-badge"
          class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-600">
      {{ $task->priority ? ucfirst($task->priority) : 'None' }}
    </span>
  </div>

  <div class="flex items-center gap-2">
    <div class="flex items-center gap-1" data-role="task-assignees">
      {{-- JS will render avatars; optionally render initial server-side too --}}
    </div>

    <div class="flex flex-wrap gap-1" data-role="task-labels">
      {{-- JS will render chips; optionally render initial server-side too --}}
    </div>
  </div>

</div>