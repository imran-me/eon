@props(['name','label','required'=>false,'type'=>'text','value'=>''])
<div class="cpc-field"><label for="cpc-{{ $name }}">{{ $label }} @if($required)<em>*</em>@endif</label><input id="cpc-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" {{ $attributes->merge($required ? ['required'=>true] : []) }}></div>
