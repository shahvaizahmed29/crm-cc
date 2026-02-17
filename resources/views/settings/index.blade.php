@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div>
    <h1 class="text-2xl font-semibold text-slate-900">Settings</h1>
    <p class="mt-1 text-sm text-slate-600">Configure agent history limit and which statuses count toward the holding limit.</p>
</div>

<form action="{{ route('settings.update') }}" method="POST" class="mt-6 space-y-6">
    @csrf
    @method('PUT')

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Agent history limit</h2>
        <p class="mt-1 text-sm text-slate-500">Agents are blocked from taking new leads when their count of leads in holding statuses reaches this limit.</p>
        <div class="mt-3">
            <label for="agent_history_limit" class="sr-only">Agent history limit</label>
            <input
                type="number"
                name="agent_history_limit"
                id="agent_history_limit"
                min="1"
                max="500"
                value="{{ old('agent_history_limit', $agentHistoryLimit) }}"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                required
            >
        </div>
        @error('agent_history_limit')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
         x-data="{
             open: false,
             selected: {{ json_encode(old('holding_status_slugs', $holdingStatusSlugs)) }},
             statuses: {{ json_encode($statuses->map(fn($s) => ['slug' => $s->slug, 'name' => $s->name])->values()) }},
             toggle(slug) {
                 const i = this.selected.indexOf(slug);
                 if (i === -1) this.selected.push(slug);
                 else this.selected.splice(i, 1);
             },
             isSelected(slug) { return this.selected.indexOf(slug) !== -1; },
             getSelectedNames() {
                 return this.selected.map(slug => this.statuses.find(s => s.slug === slug)?.name || slug).filter(Boolean);
             }
         }"
         @click.outside="open = false">
        <h2 class="text-base font-semibold text-slate-900">Holding status slugs</h2>
        <p class="mt-1 text-sm text-slate-500">Statuses that count toward the agent history limit. Click to open and choose multiple.</p>
        <div class="mt-3 relative">
            <label class="block text-sm font-medium text-slate-700 mb-1">Select statuses</label>
            <button type="button"
                    @click="open = !open"
                    class="inline-flex w-full min-h-[42px] items-center justify-between gap-2 rounded-md border border-slate-300 bg-white py-2 pl-3 pr-10 text-left text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <span class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                    <template x-if="selected.length === 0">
                        <span class="text-slate-500">Choose statuses...</span>
                    </template>
                    <template x-if="selected.length > 0">
                        <span class="flex flex-wrap gap-1.5">
                            <template x-for="name in getSelectedNames()" :key="name">
                                <span class="inline-flex items-center rounded-md bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800" x-text="name"></span>
                            </template>
                        </span>
                    </template>
                </span>
                <span class="pointer-events-none shrink-0 flex h-5 w-5 items-center text-slate-400">
                    <svg class="h-5 w-5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </span>
            </button>
            <div x-show="open"
                 x-cloak
                 x-transition
                 class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
                <template x-for="status in statuses" :key="status.slug">
                    <label class="relative flex cursor-pointer select-none items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50">
                        <input type="checkbox"
                               :checked="isSelected(status.slug)"
                               @change="toggle(status.slug)"
                               class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span x-text="status.name + ' (' + status.slug + ')'"></span>
                    </label>
                </template>
            </div>
            <template x-for="slug in selected" :key="slug">
                <input type="hidden" name="holding_status_slugs[]" :value="slug">
            </template>
        </div>
        @error('holding_status_slugs')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('holding_status_slugs.*')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <button type="submit" class="inline-flex justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Save settings</button>
    </div>
</form>
@endsection
