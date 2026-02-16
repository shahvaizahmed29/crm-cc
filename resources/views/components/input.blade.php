@props([
    'name',
    'id' => null,
    'type' => 'text',
    'value' => '',
    'required' => false,
    'placeholder' => null,
    'rows' => null,
])

@php
    $id = $id ?? $name;
    $inputClass = 'mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500';
    $rows = $rows ?? 3;
@endphp

@if($type === 'textarea')
    <textarea name="{{ $name }}" id="{{ $id }}" {{ $required ? 'required' : '' }} @if($rows) rows="{{ $rows }}" @endif
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => $inputClass]) }}>{{ old($name, $value) }}</textarea>
@else
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ old($name, $value) }}"
        {{ $required ? 'required' : '' }} placeholder="{{ $placeholder }}"
        @if($type === 'number') step="0.01" @endif
        {{ $attributes->merge(['class' => $inputClass]) }}>
@endif
