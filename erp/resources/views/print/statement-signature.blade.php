    <div class="sig">
        <div class="sig-c">
            <x-signature :company="$company" />
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>
