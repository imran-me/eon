<header class="header sticky top-0 z-10">
    <div class="flex items-center justify-between px-4 py-3">

        <!-- Left Section: Sidebar Toggle + Breadcrumb + Search -->
        <div class="flex items-center gap-3">

            <!-- Desktop Sidebar Toggle -->
            <button id="desktopSidebarButton" type="button"
                class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200/80 text-gray-500 hover:bg-white/80 hover:text-blue-600 hover:border-blue-200 hover:shadow-sm transition-all duration-200"
                aria-label="Toggle sidebar">
                <i id="desktopSidebarIcon" class="fas fa-xmark text-sm"></i>
            </button>

            <!-- Breadcrumb -->
            <nav class="hidden sm:flex items-center gap-1.5 text-sm">
                @can('view dashboard')
                    <a href="{{ route('role.dashboard', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                       class="text-gray-400 hover:text-blue-600 transition-colors duration-150">
                        <i class="fas fa-house text-xs"></i>
                    </a>
                    <i class="fas fa-chevron-right text-[9px] text-gray-300"></i>
                @endcan
                <span class="text-blue-600 font-medium">{{ $title ?? 'Dashboard' }}</span>
            </nav>

            <!-- Global Search -->
            <div class="relative">
                <div class="flex items-center w-52 md:w-64 px-3 py-2 bg-gray-100/80 border border-transparent rounded-lg focus-within:bg-white focus-within:border-blue-300 focus-within:shadow-sm transition-all duration-200">
                    <i class="fas fa-search text-gray-400 text-xs mr-2 flex-shrink-0"></i>
                    <input type="text" id="globalSearch" autocomplete="off"
                        placeholder="Search users..."
                        class="flex-1 bg-transparent text-sm text-gray-700 placeholder-gray-400 focus:outline-none min-w-0">
                </div>
                <div id="globalSearchSuggestions"
                    class="hidden absolute left-0 right-0 mt-1.5 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl z-50 divide-y divide-gray-50"></div>
            </div>
        </div>

        <!-- Right Section -->
        <div class="flex items-center gap-1.5 sm:gap-2">

            <!-- My Tasks -->
            @can('view task')
            <a href="{{ route('role.tasks.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
               class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-medium hover:bg-emerald-600 shadow-sm hover:shadow-md transition-all duration-200">
                <i class="fas fa-list-check text-xs"></i>
                <span>My Tasks</span>
            </a>
            @endcan

            <!-- Quick Task -->
            <button id="quickTaskButton" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 shadow-sm hover:shadow-md transition-all duration-200"
                aria-label="Quick Task">
                <i class="fas fa-plus text-xs"></i>
                <span class="hidden sm:inline">Quick Task</span>
            </button>

            <!-- Dark Mode Toggle -->
            <button id="darkModeToggle" type="button"
                class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-500 hover:bg-white/80 hover:text-blue-600 hover:shadow-sm border border-transparent hover:border-blue-100 transition-all duration-200"
                aria-label="Toggle dark mode">
                <i id="darkModeIcon" class="fas fa-moon text-sm"></i>
            </button>

            <!-- Notification Bell -->
            <div class="relative">
                <button id="notificationButton"
                    class="relative w-9 h-9 flex items-center justify-center rounded-lg text-gray-500 hover:bg-white/80 hover:text-blue-600 hover:shadow-sm border border-transparent hover:border-blue-100 transition-all duration-200"
                    aria-label="Notifications">
                    <i class="fas fa-bell text-sm" id="notificationBellIcon"></i>
                    <span id="notificationBadge"
                        class="notification-badge"
                        style="display:none;">0</span>
                </button>

                <!-- Notification Dropdown -->
                <div id="notificationDropdown"
                    class="notification-dropdown absolute right-0 mt-2 w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden overflow-hidden">

                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-blue-50 to-indigo-50">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-bell text-blue-600 text-xs"></i>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                            <span id="notificationDropdownCount"
                                class="hidden px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white leading-none"></span>
                        </div>
                        <button id="markAllReadBtn"
                            class="text-[11px] font-medium text-blue-600 hover:text-blue-800 px-2 py-1 rounded-md hover:bg-blue-100 transition-colors duration-150">
                            Mark all read
                        </button>
                    </div>

                    <!-- Notification List -->
                    <div id="notificationList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                        <div class="p-6 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin text-lg"></i>
                            <p class="text-xs mt-2">Loading...</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50/50">
                        <a href="{{ route('role.notifications.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                            class="flex items-center justify-center gap-1.5 text-xs text-blue-600 font-medium py-1 hover:text-blue-800 transition-colors duration-150">
                            View all notifications
                            <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="relative">
                <button id="userMenuButton"
                    class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-white/80 hover:shadow-sm border border-transparent hover:border-gray-200 transition-all duration-200">

                    <!-- Avatar with online dot -->
                    <div class="relative">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm select-none">
                            @php
                                $name = Auth::user()->teacher ? Auth::user()->teacher->full_name : Auth::user()->name;
                                $words = explode(' ', trim($name));
                                $initials = count($words) >= 2
                                    ? strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1))
                                    : strtoupper(substr($words[0], 0, 2));
                            @endphp
                            {{ $initials }}
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-400 border-2 border-white rounded-full"></span>
                    </div>

                    <div class="hidden md:block text-left">
                        <p class="text-xs font-semibold text-gray-800 leading-none mb-0.5">{{ $name }}</p>
                        <p class="text-[10px] text-gray-500 capitalize leading-none">{{ Auth::user()->getRoleNames()->first() }}</p>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-[10px] hidden md:block transition-transform duration-200" id="userMenuChevron"></i>
                </button>

                <!-- User Dropdown -->
                <div id="userDropdown"
                    class="user-dropdown absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden overflow-hidden">
                    <!-- Profile summary -->
                    <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ Auth::user()->teacher ? Auth::user()->teacher->full_name : Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ Auth::user()->email }}</p>
                    </div>
                    <div class="p-1.5 space-y-0.5">
                        @can('view dashboard')
                            <a href="{{ route('role.dashboard', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors duration-150">
                                <i class="fas fa-desktop text-gray-400 w-4 text-center text-xs"></i>
                                Dashboard
                            </a>
                        @endcan
                        <a class="changePasswordBtn flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors duration-150 cursor-pointer">
                            <i class="fas fa-lock text-gray-400 w-4 text-center text-xs"></i>
                            Change Password
                        </a>
                        <a href="{{ route('role.profile.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors duration-150">
                            <i class="fas fa-user-pen text-gray-400 w-4 text-center text-xs"></i>
                            Profile Update
                        </a>
                        <a href="{{ route('two-factor.setup') }}"
                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors duration-150">
                            <i class="fas fa-shield-halved text-gray-400 w-4 text-center text-xs"></i>
                            Manage 2FA
                        </a>
                    </div>
                    <div class="p-1.5 border-t border-gray-100">
                        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-150">
                            <i class="fas fa-right-from-bracket w-4 text-center text-xs"></i>
                            Sign Out
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Toast Notification Container -->
<div id="notificationToastContainer" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="max-width:340px;"></div>

@php
    $quickTaskRole = Str::slug(Auth::user()->getRoleNames()->first());
    $activeBoardParam = request()->route('board');
    $activeBoardId = is_object($activeBoardParam) ? ($activeBoardParam->id ?? null) : $activeBoardParam;

    $quickTaskBoards = \App\Models\Board::query()
        ->select('id', 'project_id', 'workspace_id', 'name')
        ->orderBy('name')
        ->get();

    $quickTaskProjectIds = $quickTaskBoards->pluck('project_id')->filter()->unique()->values();

    $quickTaskProjects = \App\Models\Project::query()
        ->whereIn('id', $quickTaskProjectIds)
        ->select('id', 'project_name', 'company_id', 'team_members')
        ->orderBy('project_name')
        ->get();

    $quickTaskBoardIds = $quickTaskBoards->pluck('id')->filter()->values();
    $quickTaskColumnsByBoard = collect();

    if ($quickTaskBoardIds->isNotEmpty()) {
        $quickTaskColumnsByBoard = \Illuminate\Support\Facades\DB::table('board_columns')
            ->join('columns', 'columns.id', '=', 'board_columns.column_id')
            ->whereIn('board_columns.board_id', $quickTaskBoardIds)
            ->select('board_columns.board_id', 'columns.id', 'columns.name', 'board_columns.position')
            ->orderBy('board_columns.position')
            ->orderBy('columns.id')
            ->get()
            ->groupBy('board_id');
    }

    $quickTaskDefaultBoard = $quickTaskBoards->firstWhere('id', $activeBoardId);
    if (!$quickTaskDefaultBoard) {
        $quickTaskDefaultBoard = $quickTaskBoards->first();
    }

    $quickTaskDefaultProjectId = $quickTaskDefaultBoard->project_id ?? ($quickTaskProjects->first()->id ?? null);
    if (!$quickTaskDefaultBoard && $quickTaskDefaultProjectId) {
        $quickTaskDefaultBoard = $quickTaskBoards->firstWhere('project_id', $quickTaskDefaultProjectId);
    }

    $quickTaskDefaultBoardId = $quickTaskDefaultBoard->id ?? null;
    $quickTaskDefaultWorkspaceId = $quickTaskDefaultBoard->workspace_id ?? null;
    $quickTaskDefaultColumnId = $quickTaskDefaultBoardId
        ? optional($quickTaskColumnsByBoard->get($quickTaskDefaultBoardId))->first()->id ?? null
        : null;

    $quickTaskProjectTeams = $quickTaskProjects->mapWithKeys(function ($project) {
        $memberIds = collect($project->team_members ?? [])
            ->map(fn($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        return [$project->id => $memberIds];
    });

    $quickTaskAssigneeIds = collect($quickTaskProjectTeams->values()->flatten()->all())
        ->map(fn($id) => (int) $id)
        ->push((int) Auth::id())
        ->filter()
        ->unique()
        ->values();

    $quickTaskUsers = \App\Models\User::query()
        ->whereIn('id', $quickTaskAssigneeIds)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

    $quickTaskUserProjects = [];
    foreach ($quickTaskProjectTeams as $projectId => $memberIds) {
        foreach ($memberIds as $memberId) {
            $quickTaskUserProjects[$memberId][] = (int) $projectId;
        }
    }

    $quickTaskUserProjects = collect($quickTaskUserProjects)
        ->map(fn($projectIds) => collect($projectIds)->unique()->values()->all());

    $quickTaskHasConfig = $quickTaskProjects->isNotEmpty() && $quickTaskBoards->isNotEmpty() && !empty($quickTaskDefaultColumnId);
@endphp

<!-- Quick Task Modal -->
<div id="quickTaskModal" class="fixed inset-0 z-50 hidden">
    <div id="quickTaskModalBackdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative min-h-screen flex items-end sm:items-center justify-center p-2 sm:p-4">
        <div class="w-full max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-blue-50 to-indigo-50">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-plus text-blue-600 text-xs"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-800">Create Quick Task</h3>
                </div>
                <button id="quickTaskCloseButton" type="button"
                    class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-200/70 text-gray-400 hover:text-gray-600 transition-colors duration-150"
                    aria-label="Close quick task modal">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            @if ($quickTaskHasConfig)
                <form id="quickTaskForm" method="POST"
                    action="{{ route('role.tasks.store', ['role' => $quickTaskRole]) }}"
                    enctype="multipart/form-data"
                    class="p-4 sm:p-5 space-y-4 overflow-y-auto bg-white">
                    @csrf
                    <input id="quickTaskWorkspaceId" type="hidden" name="workspace_id" value="{{ $quickTaskDefaultWorkspaceId }}">
                    <input id="quickTaskAssignedTo" type="hidden" name="assigned_to" value="">

                    <div class="space-y-3">
                        <div>
                            <input id="quickTaskTitle" name="title" type="text" required
                                placeholder="Title"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all bg-white">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <select id="quickTaskProject" name="project_id" required
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-gray-50 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                                <option value="">Select project</option>
                                @foreach ($quickTaskProjects as $project)
                                    <option value="{{ $project->id }}" {{ (int) $project->id === (int) $quickTaskDefaultProjectId ? 'selected' : '' }}>
                                        {{ $project->project_name }}
                                    </option>
                                @endforeach
                            </select>
                            <select id="quickTaskBoard" name="board_id" required
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-gray-50 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                                <option value="">Select board</option>
                                @foreach ($quickTaskBoards as $board)
                                    <option value="{{ $board->id }}"
                                        data-project-id="{{ $board->project_id }}"
                                        data-workspace-id="{{ $board->workspace_id }}"
                                        {{ (int) $board->id === (int) $quickTaskDefaultBoardId ? 'selected' : '' }}>
                                        {{ $board->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <textarea id="quickTaskDescription" name="description" rows="4"
                                placeholder="Click to add description"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all resize-none bg-white"></textarea>
                        </div>

                        
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="relative">
                                    <select id="quickTaskColumn" name="column_id" required data-search-placeholder="Search..."
                                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                                        <option value="">Select column</option>
                                        @foreach ($quickTaskColumnsByBoard as $boardId => $columns)
                                            @foreach ($columns as $column)
                                                @php
                                                    $columnMeta = match ((int) ($column->position ?? 1)) {
                                                        1 => ['icon' => 'fas fa-circle', 'color' => 'text-gray-400'],
                                                        2 => ['icon' => 'fas fa-circle', 'color' => 'text-blue-500'],
                                                        3 => ['icon' => 'fas fa-circle', 'color' => 'text-amber-500'],
                                                        4 => ['icon' => 'fas fa-check', 'color' => 'text-emerald-500'],
                                                        5 => ['icon' => 'fas fa-times', 'color' => 'text-red-500'],
                                                        default => ['icon' => 'fas fa-circle', 'color' => 'text-slate-400'],
                                                    };
                                                @endphp
                                                <option value="{{ $column->id }}"
                                                    data-board-id="{{ $boardId }}"
                                                    data-icon="{{ $columnMeta['icon'] }}"
                                                    data-color="{{ $columnMeta['color'] }}"
                                                    {{ (int) $column->id === (int) $quickTaskDefaultColumnId ? 'selected' : '' }}>
                                                    {{ $column->name }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="relative">
                                    <select id="quickTaskPriority" name="priority" data-search-placeholder="Search..."
                                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                                        <option value="" data-icon="fas fa-minus" data-color="text-gray-400">None</option>
                                        <option value="low" data-icon="fas fa-flag" data-color="text-blue-500">Low</option>
                                        <option value="medium" data-icon="fas fa-flag" data-color="text-amber-500">Medium</option>
                                        <option value="high" data-icon="fas fa-flag" data-color="text-red-500">High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="min-w-[160px]">
                                <div id="quickTaskAssigneesHidden"></div>
                                <x-ui.dropdown
                                    type="assignees"
                                    context="quick-task"
                                    :multi="true"
                                    :search="true"
                                    width="w-64"
                                    toggleClass="w-full"
                                >
                                    <x-slot:toggle>
                                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer text-xs">
                                            <span class="text-gray-700" data-role="dropdown-label">Select assignees</span>
                                            <svg class="w-3 h-3 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </x-slot:toggle>

                                    @foreach ($quickTaskUsers as $user)
                                        <x-ui.dropdown-item
                                            value="{{ $user->id }}"
                                            :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '', 'data-project-ids' => implode(',', $quickTaskUserProjects->get($user->id, []))]"
                                        >
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
                            </div>
                            <input id="quickTaskStartDate" name="start_date" type="datetime-local"
                                class="px-3 py-2 text-xs border border-gray-300 rounded-md bg-gray-50 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                            <input id="quickTaskDueDate" name="due_date" type="datetime-local"
                                class="px-3 py-2 text-xs border border-gray-300 rounded-md bg-gray-50 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all">
                        </div>

                        <div>
                            <label for="quickTaskAttachments" class="block text-[11px] font-medium text-gray-500 mb-1">Attachments (optional)</label>
                            <input id="quickTaskAttachments" name="attachments[]" type="file" multiple
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-gray-50 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none transition-all file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-blue-50 file:text-blue-600">
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button id="quickTaskCancelButton" type="button"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-all duration-150">
                            Create Task
                        </button>
                    </div>
                </form>
            @else
                <div class="p-5">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
                        Quick task setup is incomplete. Please ensure you have at least one project, board, and board column.
                    </div>
                    <div class="pt-4 flex justify-end">
                        <button id="quickTaskFallbackCloseButton" type="button"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors duration-150">
                            Close
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
