@extends('layouts.app')

@section('title', 'Callbacks')

@section('content')
<div>
    <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Callbacks</h1>
    @if(!empty($isAdminCallbacksView))
        <p class="mt-1 text-sm text-slate-600">All leads currently in Callback status, including their callback time.</p>
    @else
        <p class="mt-1 text-sm text-slate-600">Your scheduled callback reminders. Click a row to open the lead.</p>
    @endif
</div>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    @if($callbacks->isEmpty())
        <div class="px-4 py-12 text-center text-sm text-slate-500">
            @if(!empty($isAdminCallbacksView))
                No leads are currently in Callback status.
            @else
                No callback leads yet. Set a lead’s status to Callback and choose a date & time on the lead edit page to create one.
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    @if(!empty($isAdminCallbacksView))
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Lead</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned To</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Callback Time</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Last Update</th>
                            <th scope="col" class="relative px-4 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    @else
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Lead</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Remind at</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Message</th>
                            <th scope="col" class="relative px-4 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    @endif
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @if(!empty($isAdminCallbacksView))
                        @foreach($callbacks as $lead)
                            @php
                                $reminder = ($callbackReminders ?? collect())->get($lead->id);
                                $callbackAt = null;
                                if ($reminder && !empty($reminder->meta['callback_at'])) {
                                    try {
                                        $callbackAt = \Carbon\Carbon::parse((string) $reminder->meta['callback_at']);
                                    } catch (\Throwable $e) {
                                        $callbackAt = null;
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ $lead->fullName() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->assignedTo?->displayName() ?? 'Unassigned' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                    @if($callbackAt)
                                        {{ format_in_app_tz($callbackAt, 'M j, Y g:i A') }}
                                    @else
                                        <span class="text-amber-700">Not set</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ format_in_app_tz($lead->updated_at, 'M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('leads.edit', $lead) }}" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Open lead</a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        @foreach($callbacks as $notif)
                            @php $lead = $leads->get($notif->entity_id); @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">
                                    {{ $lead ? $lead->fullName() : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                    {{ format_in_app_tz($notif->notify_at, 'M j, Y g:i A') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $notif->message ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if($notif->action_url)
                                        <a href="{{ route('notifications.open', $notif) }}" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Open lead</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        @if($callbacks->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $callbacks->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
