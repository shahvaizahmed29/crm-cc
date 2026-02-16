@props([
    'name',
    'id' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    $id = $id ?? $name;
    $selected = old($name, $selected);
@endphp

<select name="{{ $name }}" id="{{ $id }}" {{ $required ? 'required' : '' }}
    {{ $attributes->merge(['class' => 'mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500']) }}>
    @if($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach($options as $value => $label)
        <option value="{{ $value }}" {{ (string) $selected === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
</select>
