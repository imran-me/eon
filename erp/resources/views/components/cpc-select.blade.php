@props(['name','label','required'=>false])
<div class="cpc-field"><label for="cpc-{{ $name }}">{{ $label }} @if($required)<em>*</em>@endif</label><select id="cpc-{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes }}>{{ $slot }}</select></div>
