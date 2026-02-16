@props([
    'label',
    'for' => null,
    'required' => false,
])

<div>
    <label for="{{ $for }}" class="block text-xs font-medium uppercase tracking-wide text-slate-600">
        {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
    </label>
    {{ $slot }}
</div>
