@extends('layouts.app')

@section('title', 'Callbacks')

@section('content')
<div>
    <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Callbacks</h1>
    <p class="mt-1 text-sm text-slate-600">Your scheduled callback reminders. Click a row to open the lead.</p>
</div>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    @if($callbacks->isEmpty())
        <div class="px-4 py-12 text-center text-sm text-slate-500">
            No callback leads yet. Set a lead’s status to Callback and choose a date & time on the lead edit page to create one.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Lead</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Remind at</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Message</th>
                        <th scope="col" class="relative px-4 py-3"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($callbacks as $notif)
                        @php $lead = $leads->get($notif->entity_id); @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">
                                {{ $lead ? $lead->fullName() : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                {{ $notif->notify_at?->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $notif->message ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if($notif->action_url)
                                    <a href="{{ route('notifications.open', $notif) }}" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Open lead</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
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
