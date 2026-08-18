{{--
    Wood Art · Projects — the delete confirmation.

    A PAGE rather than a JS confirm(): no <script> may run inside
    [data-wa-view] (CLAUDE.md), and the reference's own delete dialog does not
    ask "are you sure?" — it spells out what will be removed and what will be
    kept, with the job's real numbers in front of you. This does the same.

    It writes to wa_projects only, and only a `deleted_at` stamp.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects.edit', ['role' => request()->route('role'), 'project' => $project]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $project->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this project?</h3>
            <span class="wap-card-sub">Check the numbers below before confirming</span>
        </div>
        <div class="wap-card-body">

            {{-- The job itself, so you are certain which one this is. --}}
            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $project->name }}</div>
                        <div class="wap-proj-ext">{{ $project->ext_id }} &middot; {{ $project->client ?: 'No client' }}</div>
                    </div>
                    <span class="wap-badge">{{ $project->stage }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Value</div>
                        <div class="wap-proj-stat-value">{{ Project::money($project->value) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Cost</div>
                        <div class="wap-proj-stat-value">{{ Project::money($project->cost) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Progress</div>
                        <div class="wap-proj-stat-value">{{ (int) $project->progress }}%</div>
                    </div>
                </div>
            </div>

            <div class="wap-banner" style="margin-top:20px">
                <i class="bi bi-info-circle"></i>
                <div>
                    The project is <strong>archived, not erased</strong> &mdash; the row is kept with a
                    removal date, so the job can be restored and its code
                    <strong>{{ $project->ext_id }}</strong> is never reused by a later project.
                    Nothing else is touched: this module owns no other records yet, and when it
                    does, stock movements and book entries stay put &mdash; the material really did
                    leave the store and the money really did move.
                </div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.projects.destroy', ['role' => request()->route('role'), 'project' => $project]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $project->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'active']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
