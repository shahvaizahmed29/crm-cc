@extends('layouts.app')

@section('title', $lead->fullName())

@section('content')
<x-page-header :title="$lead->fullName()">
    <x-slot:actions>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('leads.download.txt', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Download TXT</a>
            <form action="{{ route('leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('Soft delete this lead?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">Delete</button>
            </form>
        @endif
        <a href="{{ route('leads.edit', $lead) }}" class="rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500">Edit</a>
        <a href="{{ route('leads.index') }}" class="rounded-md bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Back to list</a>
    </x-slot:actions>
</x-page-header>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
        <h2 class="text-sm font-medium text-slate-500">Lead details</h2>
        <dl class="mt-4 space-y-3">
            <div>
                <dt class="text-xs text-slate-500">Status</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $lead->status->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Address</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ $lead->address ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Date of birth</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ $lead->date_of_birth?->format('m/d/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Approx debt</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ $lead->approx_debt ? '$' . number_format($lead->approx_debt, 2) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Fees</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ $lead->fees ? '$' . number_format($lead->fees, 2) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">DNC</dt>
                <dd class="mt-0.5 text-sm">
                    @if($lead->is_dnc)
                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Yes</span>
                    @else
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">No</span>
                    @endif
                </dd>
            </div>
            @if(auth()->user()->isAdmin())
            <div>
                <dt class="text-xs text-slate-500">Assigned to</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ $lead->assignedTo?->displayName() ?? 'Unassigned' }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-xs text-slate-500">Last update</dt>
                <dd class="mt-0.5 text-sm text-slate-900">{{ format_in_app_tz($lead->updated_at, 'Y-m-d H:i:s') }}</dd>
            </div>
        </dl>
        @if($lead->details)
            <div class="mt-4 border-t border-slate-200 pt-4">
                <dt class="text-xs text-slate-500">Details</dt>
                <dd class="mt-1 text-sm text-slate-900 whitespace-pre-wrap">{{ $lead->details }}</dd>
            </div>
        @endif
    </div>
    <div class="space-y-6">
        @php
            $latestCr = $lead->creditReports->first();
        @endphp
        <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
            <h2 class="text-sm font-medium text-slate-500">Credit Report</h2>

            <div class="mt-3 text-sm text-slate-700">
                <p>Status: <span class="font-semibold">{{ $latestCr ? ucfirst($latestCr->status) : 'No request yet' }}</span></p>
                @if($latestCr && $latestCr->comment)
                    <p class="mt-1 text-xs text-slate-600">Comment: {{ $latestCr->comment }}</p>
                @endif
            </div>

            @if(auth()->user()->isAdmin() && $latestCr)
                <form action="{{ route('credit-reports.result', $latestCr) }}" method="POST" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
                    @csrf
                    <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="sent">Sent</option>
                        <option value="notfound">Not Found</option>
                    </select>
                    <input type="file" name="cr_file" accept=".pdf" class="rounded-md border border-slate-300 bg-slate-100 cursor-pointer px-3 py-2 text-sm">
                    <input type="text" name="comment" placeholder="Comment" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Update CR</button>
                </form>
            @endif
        </div>

        <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
            <h2 class="text-sm font-medium text-slate-500">Phone numbers</h2>
            @if($lead->phones->isEmpty())
                <p class="mt-2 text-sm text-slate-500">No phone numbers.</p>
            @else
                <ul class="mt-2 space-y-1">
                    @foreach($lead->phones as $phone)
                        <li class="text-sm text-slate-900">{{ $phone->phone }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
            <h2 class="text-sm font-medium text-slate-500">Email addresses</h2>
            @if($lead->emails->isEmpty())
                <p class="mt-2 text-sm text-slate-500">No email addresses.</p>
            @else
                <ul class="mt-2 space-y-1">
                    @foreach($lead->emails as $email)
                        <li class="text-sm text-slate-900">{{ $email->email }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-lg bg-white p-6 shadow ring-1 ring-slate-200">
            <h2 class="text-sm font-medium text-slate-500">Callbacks <span class="font-semibold text-slate-700">({{ $callbackNotifications->count() }})</span></h2>
            <p class="mt-1 text-xs text-slate-500">All callback reminders created for this lead.</p>
            @if($callbackNotifications->isEmpty())
                <p class="mt-3 text-sm text-slate-500">No callbacks yet.</p>
            @else
                <ul class="mt-3 space-y-3 divide-y divide-slate-100">
                    @foreach($callbackNotifications as $notif)
                        <li class="pt-3 first:pt-0">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $notif->title }}</p>
                                    @if($notif->message)
                                        <p class="mt-0.5 text-xs text-slate-600">{{ $notif->message }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500">
                                        Notify at: {{ format_in_app_tz($notif->notify_at, 'M j, Y g:i A') }}
                                        @if($notif->createdBy)
                                            · Created by {{ $notif->createdBy->displayName() }}
                                        @endif
                                        @if($notif->read_at)
                                            · Read
                                        @endif
                                    </p>
                                </div>
                                @if($notif->action_url)
                                    <a href="{{ route('notifications.open', $notif) }}" class="shrink-0 rounded-md bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-sky-500">Open</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
