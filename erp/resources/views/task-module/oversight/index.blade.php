@extends('layout.app')

@section('meta-information')
    <title>Employee Task Oversight</title>
@endsection

{{--
  EMPLOYEE TASK OVERSIGHT — the picker.

  Pick a person, open their board. Everything on this page exists to answer one
  question before you click: who needs looking at? That is why the cards lead
  with flags, locks and overdue rather than with a headshot, and why the
  controller sorts flagged people to the front.

  Every selector here is prefixed eto- and every rule lives in this file. The
  ERP's shared bundle is loaded by twelve companies on every page and a screen
  only the super admin can reach has no business in it.
--}}

@section('css')
<style>
    .eto-wrap { padding: 1.25rem; }

    .eto-head {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 1rem; flex-wrap: wrap; margin-bottom: 1.1rem;
    }
    .eto-title { font-size: 1.35rem; font-weight: 700; color: #111827; line-height: 1.2; }
    .eto-sub   { font-size: .82rem; color: #6b7280; margin-top: .2rem; }

    .eto-search { display: flex; align-items: center; gap: .5rem; }
    .eto-search input {
        width: 280px; max-width: 60vw;
        padding: .55rem .8rem .55rem 2.2rem;
        border: 1px solid #e5e7eb; border-radius: 9px;
        font-size: .85rem; background: #fff;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .eto-search input:focus {
        outline: none; border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    }
    .eto-search-box { position: relative; }
    .eto-search-box > i {
        position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
        color: #9ca3af; font-size: .8rem; pointer-events: none;
    }
    .eto-search button {
        padding: .55rem 1rem; border-radius: 9px; font-size: .85rem; font-weight: 600;
        background: #2563eb; color: #fff; border: 0; cursor: pointer;
        transition: background .15s ease;
    }
    .eto-search button:hover { background: #1d4ed8; }
    .eto-clear {
        font-size: .8rem; color: #6b7280; text-decoration: none;
        padding: .55rem .6rem; border-radius: 8px;
    }
    .eto-clear:hover { background: #f3f4f6; color: #374151; }

    .eto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(304px, 1fr));
        gap: .9rem;
    }

    /* The card. Left rail carries the verdict colour so a flagged person is
       findable by scanning one edge, the way the board cards do it. */
    .eto-card {
        position: relative;
        background: #fff; border: 1px solid #e9ecef; border-left: 4px solid #d1d5db;
        border-radius: 11px; padding: .95rem 1rem .8rem;
        text-decoration: none; color: inherit; display: block;
        box-shadow: 0 1.5px 3px rgba(17,21,45,.07);
        transition: box-shadow .2s ease, border-color .2s ease, top .2s ease;
        top: 0;
    }
    .eto-card:hover {
        top: -3px;
        border-color: #bfdbfe; border-left-color: #2563eb;
        box-shadow: 0 12px 22px -12px rgba(17,21,45,.32), 0 3px 6px -3px rgba(17,21,45,.12);
    }
    .eto-card.is-flagged  { border-left-color: #ef4444; }
    .eto-card.is-locked   { border-left-color: #f59e0b; }
    .eto-card.is-idle     { opacity: .72; }

    .eto-person { display: flex; align-items: center; gap: .7rem; }
    .eto-avatar {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg,#dbeafe,#bfdbfe);
        color: #1d4ed8; font-weight: 700; font-size: .95rem;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    .eto-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .eto-name {
        font-weight: 600; font-size: .92rem; color: #111827;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .eto-meta { font-size: .72rem; color: #9ca3af; margin-top: .1rem;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .eto-pill {
        font-size: .62rem; font-weight: 700; letter-spacing: .03em;
        text-transform: uppercase; padding: .12rem .42rem; border-radius: 5px;
    }
    .eto-pill.active   { background: #ecfdf5; color: #059669; }
    .eto-pill.inactive { background: #f3f4f6; color: #6b7280; }
    .eto-pill.resigned { background: #fef2f2; color: #b91c1c; }

    /* Numbers row. Fixed columns so the eye can compare down the grid. */
    .eto-stats {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: .35rem; margin-top: .85rem;
        border-top: 1px solid #f1f3f5; padding-top: .7rem;
    }
    .eto-stat { text-align: center; }
    .eto-stat-v { font-size: .95rem; font-weight: 700; color: #111827; line-height: 1.15; }
    .eto-stat-l { font-size: .6rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; margin-top: .1rem; }
    .eto-stat.warn .eto-stat-v { color: #b45309; }
    .eto-stat.bad  .eto-stat-v { color: #dc2626; }
    .eto-stat.mute .eto-stat-v { color: #d1d5db; }

    /* Rating, bottom right — a row of stars rather than a number, so it reads
       as a judgement and not as one more count. */
    .eto-rating {
        display: flex; align-items: center; justify-content: space-between;
        gap: .5rem; margin-top: .7rem;
        border-top: 1px solid #f1f3f5; padding-top: .6rem;
        font-size: .72rem; color: #6b7280;
    }
    .eto-stars { letter-spacing: .06em; color: #f59e0b; font-size: .78rem; }
    .eto-stars .off { color: #e5e7eb; }

    .eto-badges { position: absolute; top: .7rem; right: .8rem; display: flex; gap: .3rem; }
    .eto-badge {
        font-size: .62rem; font-weight: 700; padding: .15rem .4rem; border-radius: 5px;
        display: inline-flex; align-items: center; gap: .2rem;
    }
    .eto-badge.flag { background: #fef2f2; color: #dc2626; }
    .eto-badge.lock { background: #fffbeb; color: #b45309; }

    .eto-empty {
        background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px;
        padding: 3rem 1.5rem; text-align: center; color: #6b7280;
    }
    .eto-empty i { font-size: 2rem; color: #d1d5db; display: block; margin-bottom: .7rem; }
</style>
@endsection

@section('main-content')
@php
    $role = Str::slug(auth()->user()->getRoleNames()->first());
@endphp

<div class="eto-wrap">

    <div class="eto-head">
        <div>
            <div class="eto-title">Employee Task Oversight</div>
            <div class="eto-sub">
                Open anyone&rsquo;s task board exactly as they see it &mdash; assign, comment,
                restrict, flag and rate their work.
            </div>
        </div>

        <form method="GET" action="{{ route('role.task-oversight.index', ['role' => $role]) }}" class="eto-search">
            <div class="eto-search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search name, email, ID or phone…" autocomplete="off">
            </div>
            <button type="submit">Search</button>
            @if($q !== '')
                <a class="eto-clear" href="{{ route('role.task-oversight.index', ['role' => $role]) }}">Clear</a>
            @endif
        </form>
    </div>

    @if($people->isEmpty())
        <div class="eto-empty">
            <i class="fas fa-user-slash"></i>
            <div style="font-weight:600;color:#374151;">
                {{ $q !== '' ? 'Nobody matches “' . $q . '”' : 'No people on file yet' }}
            </div>
            <div style="font-size:.82rem;margin-top:.3rem;">
                {{ $q !== '' ? 'Try a different name, email or employee ID.' : 'Boards belong to people — add someone first.' }}
            </div>
        </div>
    @else
        <div class="eto-grid">
            @foreach($people as $p)
                @php
                    $s = $stats[$p->id] ?? null;
                    $total   = (int) ($s->total ?? 0);
                    $done    = (int) ($s->done ?? 0);
                    $overdue = (int) ($s->overdue ?? 0);
                    $flags   = (int) ($s->flags ?? 0);
                    $locks   = (int) ($s->locks ?? 0);
                    $avg     = $s->avg_rating ?? null;

                    $classes = $flags ? 'is-flagged' : ($locks ? 'is-locked' : '');
                    if (!$total) { $classes .= ' is-idle'; }

                    $status = strtolower($p->status ?? '');
                    $initials = collect(explode(' ', trim($p->name)))
                        ->filter()->take(2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode('');
                @endphp

                <a class="eto-card {{ $classes }}"
                   href="{{ route('role.task-oversight.employee', ['role' => $role, 'employee' => $p->id]) }}"
                   title="Open {{ $p->name }}'s task board">

                    @if($flags || $locks)
                        <div class="eto-badges">
                            @if($flags)
                                <span class="eto-badge flag"><i class="fas fa-flag"></i> {{ $flags }}</span>
                            @endif
                            @if($locks)
                                <span class="eto-badge lock"><i class="fas fa-lock"></i> {{ $locks }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="eto-person">
                        <div class="eto-avatar">
                            @if($p->image)
                                <img src="{{ asset($p->image) }}" alt="{{ $p->name }}">
                            @else
                                {{ $initials ?: '?' }}
                            @endif
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div class="eto-name">{{ $p->name }}</div>
                            <div class="eto-meta">
                                {{ $p->employee_id_no ? $p->employee_id_no . ' · ' : '' }}{{ $p->email }}
                            </div>
                        </div>
                        @if($status)
                            <span class="eto-pill {{ $status }}">{{ $status }}</span>
                        @endif
                    </div>

                    <div class="eto-stats">
                        <div class="eto-stat {{ $total ? '' : 'mute' }}">
                            <div class="eto-stat-v">{{ $total }}</div>
                            <div class="eto-stat-l">Tasks</div>
                        </div>
                        <div class="eto-stat {{ $done ? '' : 'mute' }}">
                            <div class="eto-stat-v">{{ $done }}</div>
                            <div class="eto-stat-l">Done</div>
                        </div>
                        <div class="eto-stat {{ $overdue ? 'bad' : 'mute' }}">
                            <div class="eto-stat-v">{{ $overdue }}</div>
                            <div class="eto-stat-l">Overdue</div>
                        </div>
                        <div class="eto-stat {{ $locks ? 'warn' : 'mute' }}">
                            <div class="eto-stat-v">{{ $locks }}</div>
                            <div class="eto-stat-l">Locked</div>
                        </div>
                    </div>

                    <div class="eto-rating">
                        <span>
                            @if($avg !== null)
                                <span class="eto-stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star {{ $i <= round($avg) ? '' : 'off' }}"></i>
                                    @endfor
                                </span>
                                <span style="margin-left:.3rem;font-weight:600;color:#374151;">{{ number_format($avg, 1) }}</span>
                                <span style="color:#9ca3af;">· {{ $s->rated }} rated</span>
                            @else
                                <span style="color:#9ca3af;">Not rated yet</span>
                            @endif
                        </span>
                        <span style="color:#2563eb;font-weight:600;white-space:nowrap;">
                            Open board <i class="fas fa-arrow-right" style="font-size:.65rem;"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
