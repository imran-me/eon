{{-- Renders the company's uploaded account signature (set in Company Settings) as a standalone
     image, meant to sit ABOVE the existing "Authorized Signatory" line/caption — the way a pen
     signature sits over a line — not inside the same bordered box as the line itself.
     Renders nothing when no signature is on file, so callers must keep the original
     line/caption markup untouched below it. --}}
@props(['company' => null, 'maxHeight' => '65px', 'maxWidth' => '180px'])
@php
    $hasSignature = !empty(optional($company)->signature) && file_exists(public_path($company->signature));
@endphp
@if($hasSignature)
<img src="{{ asset($company->signature) }}" alt="Signature" style="display:block;margin:0 auto 2px;max-height: {{ $maxHeight }}; max-width: {{ $maxWidth }}; object-fit: contain;">
@endif
