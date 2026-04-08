@extends('layouts.app')

@section('title', 'Agent Queue')

@section('content')
<x-page-header title="My Queue" />

<p class="mt-1 text-sm text-slate-600">
    You can only work on one active lead at a time. Once submitted with a non-New status, you can take another lead.
</p>

<div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
    <p class="text-sm text-slate-700">
        Holding statuses count:
        <span class="font-semibold">{{ $holdingCount }}</span> / {{ $historyLimit }}
    </p>
    <p class="mt-1 text-xs text-slate-500">
        Holding statuses: Need To Reconnect, Callback, Payoff, Maxout, Drop.
    </p>
</div>

@if($activeLead)
    <div class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-5">
        <h2 class="text-lg font-semibold text-slate-900">Active Lead Locked To You</h2>
        <p class="mt-2 text-sm text-slate-700">
            {{ $activeLead->fullName() }} (status: {{ $activeLead->status->name }})
        </p>
        <p class="mt-1 text-sm text-slate-600">
            You must submit this lead with a non-New status before taking a new one.
        </p>
        <div class="mt-4">
            <a href="{{ route('leads.edit', $activeLead) }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">
                Work on this lead
            </a>
        </div>
    </div>
@elseif($isBlockedByHistory)
    <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h2 class="text-lg font-semibold text-slate-900">Queue Blocked</h2>
        <p class="mt-2 text-sm text-slate-700">
            You already have {{ $holdingCount }} leads in holding statuses.
            @if(isset($enforcedHistoryLimit))
            Current enforced limit is {{ $enforcedHistoryLimit }}.
            @endif
            Update at least one history lead to a final status (not Need To Reconnect, Callback, Payoff, Maxout, Drop) to continue.
        </p>
        <div class="mt-4">
            <a href="{{ route('leads.index') }}" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                Open Leads
            </a>
        </div>
    </div>
@elseif($candidateLead)
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Next Available Lead</h2>
        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-slate-500">Name</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $candidateLead->fullName() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Status</dt>
                <dd class="text-sm font-medium text-slate-900">{{ $candidateLead->status->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Debt</dt>
                <dd class="text-sm text-slate-900">
                    @if($candidateLead->approx_debt) ${{ number_format($candidateLead->approx_debt, 2) }} @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Address</dt>
                <dd class="text-sm text-slate-900">{{ $candidateLead->address ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-slate-500">Phones (up to 5)</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @php $tones = ['bg-emerald-100 text-emerald-800 ring-emerald-200', 'bg-sky-100 text-sky-800 ring-sky-200', 'bg-violet-100 text-violet-800 ring-violet-200', 'bg-amber-100 text-amber-800 ring-amber-200', 'bg-rose-100 text-rose-800 ring-rose-200']; @endphp
                    @for($idx = 0; $idx < 5; $idx++)
                    @php $phone = $candidateLead->phones->values()->get($idx); @endphp
                    <span class="inline-flex max-w-full items-center truncate rounded px-2 py-0.5 text-xs font-semibold ring-1 {{ $tones[$idx] }}">
                        <span class="mr-0.5 shrink-0 opacity-70">{{ $idx + 1 }}.</span><span class="truncate">{{ $phone?->phone ?? '—' }}</span>
                    </span>
                    @endfor
                </dd>
            </div>
        </dl>

        <div class="mt-5 flex gap-2">
            <form method="POST" action="{{ route('agent.queue.skip') }}">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $candidateLead->id }}">
                <button type="submit" class="rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-500">
                    Skip
                </button>
            </form>
            <form method="POST" action="{{ route('agent.queue.take') }}">
                @csrf
                <input type="hidden" name="lead_id" value="{{ $candidateLead->id }}">
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Take Lead
                </button>
            </form>
        </div>
    </div>
@else
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold text-slate-900">No Available Leads</h2>
        <p class="mt-2 text-sm text-slate-600">There are currently no unassigned leads with New status.</p>
    </div>
@endif
@endsection
