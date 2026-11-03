    <div class="hdr">
        <div>
            @if(optional($company)->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
            <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

