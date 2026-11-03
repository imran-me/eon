{{-- User Role Setting header + its two pages (owner, 2026-11-01): Assign Role | Employees with Roles --}}
<style>
    .urs-head{display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap;}
    .urs-head-ic{width:42px;height:42px;border-radius:12px;background:#ccfbf1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .urs-title{font-size:22px;font-weight:750;letter-spacing:-.03em;margin:0;line-height:1.2;}
    .urs-sub{font-size:13px;color:#64748b;margin:2px 0 0;}
    .urs-tabs{display:flex;gap:4px;padding:4px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:11px;margin-left:8px;}
    .urs-tab{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 14px;border-radius:8px;font-size:13px;font-weight:600;color:#475569;text-decoration:none;white-space:nowrap;}
    .urs-tab:hover{background:#e2e8f0;color:#1e293b;}
    .urs-tab.on{background:#fff;color:#0f766e;box-shadow:0 1px 2px rgba(5,22,80,.08),0 2px 6px -2px rgba(5,22,80,.10);}
    .urs-tab b{font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:#e2e8f0;color:#475569;}
    .urs-tab.on b{background:#ccfbf1;color:#0f766e;}
    .urs-test{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;font-weight:600;white-space:nowrap;}
</style>
@php
    $ursCount = null;
    $ursLive = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('user_role_settings')) {
            $ursCount = \Illuminate\Support\Facades\DB::table('user_role_settings')->count();
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_role_settings', 'applied') && \App\Support\UserRoleSetting\Access::masterOn()) {
                $ursLive = \Illuminate\Support\Facades\DB::table('user_role_settings')->where('applied', true)->count();
            }
        }
    } catch (\Throwable $e) {
        $ursCount = null;
    }
@endphp
<div class="urs-head">
    <div class="urs-head-ic"><i class="fas fa-user-shield"></i></div>
    <div style="min-width:0">
        <h1 class="urs-title">User Role Setting</h1>
    </div>
    <nav class="urs-tabs" aria-label="User Role Setting pages">
        <a href="{{ route('role.user-role-settings.index', ['role' => $role]) }}" class="urs-tab {{ $active === 'assign' ? 'on' : '' }}"><i class="fas fa-sitemap"></i> Assign Role</a>
        <a href="{{ route('role.user-role-settings.employees', ['role' => $role]) }}" class="urs-tab {{ $active === 'employees' ? 'on' : '' }}"><i class="fas fa-users"></i> Employees with Roles @if($ursCount !== null)<b>{{ $ursCount }}</b>@endif</a>
    </nav>
    @if($ursLive)
        <span class="urs-test" style="background:#dcfce7;border-color:#86efac;color:#15803d"><i class="fas fa-check-circle"></i> Live for {{ $ursLive }} {{ \Illuminate\Support\Str::plural('person', $ursLive) }} — everyone else on the old system</span>
    @else
        <span class="urs-test"><i class="fas fa-flask"></i> Test — not applied to anyone yet</span>
    @endif
</div>
@if(!empty($sub))<p class="urs-sub" style="margin:-6px 0 12px">{{ $sub }}</p>@endif
