@include('woodart::construction.styles')
@php
    $headerPC = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $switchSearch = request()->query('switch_q', '');
    $switchSearch = is_string($switchSearch) ? mb_substr(trim($switchSearch), 0, 160) : '';
    $switchProjects = \Illuminate\Support\Facades\DB::table('expense_departments')->where('company_id', $headerPC::id())->whereNull('deleted_at')
        ->when($switchSearch !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$switchSearch.'%')->orWhere('id', $switchSearch)))
        ->orderBy('name')->limit(21)->get(['id', 'name', 'status']);
    $headerStatement = $st ?? $statement ?? null;
    $headerBase = $headerPC::route('project', ['department' => $dept->id]);
    $headerCanEdit = auth()->user()?->can('view all expense') && auth()->user()?->can('approve expense');
    $headerTerms = \Illuminate\Support\Facades\DB::table('project_terms')->where('expense_department_id', $dept->id)->first();
    $headerMemberIds = json_decode($headerTerms->team_member_ids ?? '[]', true) ?: [];
    if ($headerTerms?->manager_user_id) $headerMemberIds = array_values(array_unique([(int) $headerTerms->manager_user_id, ...$headerMemberIds]));
    $headerMemberQuery = \Illuminate\Support\Facades\DB::table('users')->whereIn('id', $headerMemberIds)->limit(5)->select(['id', 'name']);
    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'image')) $headerMemberQuery->addSelect('image');
    $headerMembers = $headerMemberQuery->get();
    $headerStatus = match($headerTerms->construction_status ?? null) {'planning' => 'Planning', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed', default => ($dept->status ? 'Active' : 'Inactive')};
@endphp
<header class="wa-cpm wa-cpm-header">
    <div class="wa-cpm-breadcrumb"><a href="{{ $headerPC::route('', ['section' => 'projects']) }}">Construction / Projects</a> / {{ $dept->name }}</div>
    <div class="wa-cpm-header-row">
        <div class="wa-cpm-heading">
            <span class="wa-cpm-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span>
            <div>
                <details class="wa-cpm-switcher" @if($switchSearch !== '') open @endif>
                    <summary><h2>{{ $dept->name }}</h2><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div class="wa-cpm-switch-menu">
                        <form method="GET" action="{{ url()->current() }}">
                            @if(isset($workspace))<input type="hidden" name="view" value="{{ $workspace }}">@endif
                            <label class="wa-cpm-note" for="wa-cpm-switch-search">Switch project · search name or internal ID</label>
                            <div class="wa-cpm-tools"><input id="wa-cpm-switch-search" type="search" name="switch_q" maxlength="160" value="{{ $switchSearch }}"><button type="submit" class="wap-btn wap-btn-ghost">Search</button></div>
                        </form>
                        @forelse($switchProjects->take(20) as $switchProject)
                            <a href="{{ $headerPC::route('project.summary', ['department' => $switchProject->id]) }}" @if($switchProject->id === $dept->id) aria-current="page" @endif><span>{{ $switchProject->name }}</span><span class="wa-cpm-badge {{ $switchProject->status ? '' : 'is-muted' }}">{{ $switchProject->status ? 'Active' : 'Inactive' }}</span></a>
                        @empty<div class="wa-cpm-empty">No projects match your search.</div>@endforelse
                        @if($switchProjects->count() > 20)<p class="wa-cpm-note">Showing 20 results. Refine your search for more.</p>@endif
                    </div>
                </details>
                <span class="wa-cpm-note">{{ ($headerStatement['client'] ?? null)?->name ?? $headerPC::name() }} · Internal project ID #{{ $dept->id }} <span class="wa-cpm-badge {{ $dept->status ? '' : 'is-muted' }}">{{ $dept->status ? 'Active' : 'Inactive' }}</span></span>
            </div>
        </div>
        <div class="wa-cpm-head-team" aria-label="Assigned project team">@foreach($headerMembers as $member)<span title="{{ $member->name }}">@if($member->image ?? null)<img src="{{ asset($member->image) }}" alt="">@else{{ strtoupper(mb_substr($member->name, 0, 2)) }}@endif</span>@endforeach @if(count($headerMemberIds) > 5)<b>+{{ count($headerMemberIds) - 5 }}</b>@endif</div>
        <span class="wa-cpm-project-status">{{ $headerStatus }} <i class="bi bi-chevron-down"></i></span>
        <div class="wa-cpm-head-dates"><span>Start Date<strong>{{ $headerTerms?->start_date ? \Carbon\Carbon::parse($headerTerms->start_date)->format('d M Y') : 'Not set' }}</strong></span><span>End Date<strong>{{ $headerTerms?->end_date ? \Carbon\Carbon::parse($headerTerms->end_date)->format('d M Y') : 'Not set' }}</strong></span><div class="wa-cpm-mini-progress"><i style="width:{{ (int) ($headerTerms->progress_percent ?? 0) }}%"></i></div><b>{{ $headerTerms?->progress_percent === null ? '—' : $headerTerms->progress_percent.'%' }}</b></div>
        <div class="wa-cpm-tools">
            @if($headerCanEdit)<a class="wap-btn wap-btn-primary" href="{{ $headerPC::route('bulk', ['project' => $dept->id]) }}"><i class="bi bi-plus-lg"></i> New Expense</a><a class="wap-btn wap-btn-ghost" href="{{ $headerBase }}?view=collections">Record Collection</a>@endif
            <details class="wa-cpm-columns"><summary class="wap-btn wap-btn-ghost">More <i class="bi bi-chevron-down"></i></summary><div class="wa-cpm-columns-menu"><a href="{{ $headerPC::route('project.summary', ['department' => $dept->id, 'mode' => 'posting']) }}">Accounting &amp; site team</a><br><br><a href="{{ $headerPC::route('project.journals', ['department' => $dept->id]) }}">Project journals</a><br><br><a href="{{ $headerPC::route('', ['section' => 'projects']) }}">All projects</a></div></details>
        </div>
    </div>
</header>
