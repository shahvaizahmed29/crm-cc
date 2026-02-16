@props([
    'title',
    'backUrl' => null,
    'backText' => 'Back to list',
])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
    @if(isset($actions))
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @elseif($backUrl)
        <a href="{{ $backUrl }}" class="inline-flex w-fit rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300">{{ $backText }}</a>
    @endif
</div>
