@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div>
    <h1 class="text-2xl font-semibold text-slate-900">Settings</h1>
    <p class="mt-1 text-sm text-slate-600">Configure agent/sub-agent history limits and which statuses count toward holding limits.</p>
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

        <div class="mt-5">
            <h3 class="text-sm font-semibold text-slate-900">Sub agent history limit</h3>
            <p class="mt-1 text-sm text-slate-500">Sub agents cannot receive new deal-sheet assignments when their holding count reaches this limit.</p>
            <div class="mt-2">
                <label for="sub_agent_history_limit" class="sr-only">Sub agent history limit</label>
                <input
                    type="number"
                    name="sub_agent_history_limit"
                    id="sub_agent_history_limit"
                    min="1"
                    max="500"
                    value="{{ old('sub_agent_history_limit', $subAgentHistoryLimit) }}"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                    required
                >
            </div>
            @error('sub_agent_history_limit')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Round-robin: skipped leads</h2>
        <p class="mt-1 text-sm text-slate-500">Skipped leads are hidden globally from all agents until at least this many other leads are shown. If all non-skipped leads are exhausted, skipped leads are shown again.</p>
        <div class="mt-3">
            <label for="round_robin_leads_before_skipped_reshown" class="sr-only">Leads before skipped lead is shown again</label>
            <input
                type="number"
                name="round_robin_leads_before_skipped_reshown"
                id="round_robin_leads_before_skipped_reshown"
                min="2000"
                max="10000"
                value="{{ old('round_robin_leads_before_skipped_reshown', $roundRobinLeadsBeforeSkippedReshown ?? 2000) }}"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                required
            >
        </div>
        @error('round_robin_leads_before_skipped_reshown')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <div class="mt-4 border-t border-slate-200 pt-4">
            <p class="text-xs text-slate-500">Need to immediately allow previously skipped leads back into queue? Reset the skipped queue state.</p>
            <button
                type="submit"
                name="reset_round_robin_queue"
                value="1"
                class="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                onclick="return confirm('Reset round-robin skipped queue state and make skipped leads immediately available?')"
            >
                Reset Skipped Queue State
            </button>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Queue lead order</h2>
        <p class="mt-1 text-sm text-slate-500">Controls how unassigned new leads are ordered in round-robin by <code>id</code> (oldest/newest).</p>
        <div class="mt-3">
            <label for="queue_lead_order_direction" class="sr-only">Queue lead order direction</label>
            <select
                name="queue_lead_order_direction"
                id="queue_lead_order_direction"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                required
            >
                <option value="asc" {{ old('queue_lead_order_direction', $queueLeadOrderDirection ?? 'asc') === 'asc' ? 'selected' : '' }}>ASC (oldest first)</option>
                <option value="desc" {{ old('queue_lead_order_direction', $queueLeadOrderDirection ?? 'asc') === 'desc' ? 'selected' : '' }}>DESC (newest first)</option>
            </select>
        </div>
        @error('queue_lead_order_direction')
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

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">CR notification sound</h2>
        <p class="mt-1 text-sm text-slate-500">Enable or disable CR alert tone in admin navbar polling.</p>
        <div class="mt-3 flex items-center gap-3">
            <input type="hidden" name="cr_sound_notifications_enabled" value="0">
            <input
                type="checkbox"
                name="cr_sound_notifications_enabled"
                id="cr_sound_notifications_enabled"
                value="1"
                {{ old('cr_sound_notifications_enabled', $crSoundNotificationsEnabled ? '1' : '0') === '1' ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
            >
            <label for="cr_sound_notifications_enabled" class="text-sm font-medium text-slate-700">Play CR alert sound notifications</label>
        </div>
        @error('cr_sound_notifications_enabled')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Callback reminder window</h2>
        <p class="mt-1 text-sm text-slate-500">How many minutes before callback time the reminder should notify the creator.</p>
        <div class="mt-3">
            <label for="callback_reminder_minutes" class="sr-only">Callback reminder minutes</label>
            <input
                type="number"
                name="callback_reminder_minutes"
                id="callback_reminder_minutes"
                min="1"
                max="1440"
                value="{{ old('callback_reminder_minutes', $callbackReminderMinutes ?? 15) }}"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                required
            >
        </div>
        @error('callback_reminder_minutes')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">New leads notification threshold</h2>
        <p class="mt-1 text-sm text-slate-500">Notify admins of new/unassigned leads only when the current new leads count is below this number. Leave empty to always notify.</p>
        <div class="mt-3">
            <label for="new_leads_notification_threshold" class="sr-only">New leads notification threshold</label>
            <input
                type="number"
                name="new_leads_notification_threshold"
                id="new_leads_notification_threshold"
                min="0"
                max="10000"
                placeholder="e.g. 10"
                value="{{ old('new_leads_notification_threshold', $newLeadsNotificationThreshold) }}"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
            >
        </div>
        @error('new_leads_notification_threshold')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Display timezone</h2>
        <p class="mt-1 text-sm text-slate-500">All dates and times in the app are shown in this timezone. Date filters (e.g. From/To) are interpreted in this timezone. Database stores UTC.</p>
        <div class="mt-3">
            <label for="app_timezone" class="block text-sm font-medium text-slate-700">Timezone</label>
            <select
                name="app_timezone"
                id="app_timezone"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
            >
                @foreach($timezoneOptions as $opt)
                    <option value="{{ $opt['value'] }}" {{ old('app_timezone', $appTimezone) === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </div>
        @error('app_timezone')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mt-1 text-sm text-slate-500">Only these IP addresses can access the application. One IP per line. Leave empty to allow all IPs.</p>
        <div class="mt-3">
            <label for="ip_whitelist" class="block text-sm font-medium text-slate-700">Whitelisted IPs</label>
            <textarea
                name="ip_whitelist"
                id="ip_whitelist"
                rows="6"
                placeholder="127.0.0.1&#10;192.168.1.1"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
            >{{ old('ip_whitelist', implode("\n", $ipWhitelist)) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">One IP per line. Comma-separated is also accepted.</p>
        </div>
        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <p class="font-medium">Recovery URL</p>
            <p class="mt-1">If you remove all IPs by mistake, use the recovery page to add your IP again: <code class="break-all rounded bg-amber-100 px-1">{{ url('ip-whitelist-recovery') }}?token=YOUR_TOKEN</code></p>
            <p class="mt-2 text-xs">Set <code class="rounded bg-amber-100 px-1">IP_WHITELIST_RECOVERY_TOKEN</code> in your <code class="rounded bg-amber-100 px-1">.env</code> file and use it as the <code class="rounded bg-amber-100 px-1">token</code> query parameter. Keep this URL and token secret.</p>
        </div>
        @error('ip_whitelist')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <button type="submit" class="inline-flex justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Save settings</button>
    </div>
</form>
@endsection
