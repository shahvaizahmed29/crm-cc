@extends('layouts.app')

@section('title', 'Leads')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $isAgent = auth()->user()->isAgent();
@endphp
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Leads</h1>
        <p class="mt-1 text-sm text-slate-600">
            @if($isAdmin)
                Manage non-new leads. New leads are available on the New Leads page.
            @else
                Manage your assigned leads by status.
            @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($isAdmin)
        <a href="{{ route('leads.export') }}" class="inline-flex justify-center rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-500">Download CSV</a>
        <a href="{{ route('leads.new.index') }}" class="inline-flex justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Open New Leads</a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('leads.index') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3">
        <h2 class="text-sm font-semibold text-slate-900">Filter Leads</h2>
        <p class="text-xs text-slate-500">Use date range and optional filters to narrow results.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div>
            <label for="date_from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">From Date</label>
            <input
                type="date"
                id="date_from"
                name="date_from"
                value="{{ request('date_from') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
            >
        </div>

        <div>
            <label for="date_to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">To Date</label>
            <input
                type="date"
                id="date_to"
                name="date_to"
                value="{{ request('date_to') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
            >
        </div>

        @if($isAdmin || ($isAgent && $statuses->isNotEmpty()))
            <div>
                <label for="status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Status</label>
                <select name="status" id="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->id }}" {{ request('status') == (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($isAdmin)
            <div>
                <label for="dnc" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">DNC</label>
                <select name="dnc" id="dnc" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All</option>
                    <option value="1" {{ request('dnc') === '1' ? 'selected' : '' }}>DNC only</option>
                    <option value="0" {{ request('dnc') === '0' ? 'selected' : '' }}>Non-DNC</option>
                </select>
            </div>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Apply Filters</button>
        <a href="{{ route('leads.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
</form>

@if($isAgent && isset($holdingCount))
    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-700">
            Holding statuses count:
            <span class="font-semibold">{{ $holdingCount }}</span> / {{ $historyLimit }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
            These are your leads in holding statuses: {{ $statuses->isNotEmpty() ? $statuses->pluck('name')->join(', ') : '—' }}.
            @if($holdingCount >= $historyLimit)
                Queue is locked. Update one lead to a non-holding status to unlock new lead assignment.
            @endif
        </p>
    </div>
@endif

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total Debt</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Last Update</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">DNC</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Contacts</th>
                @if($isAdmin)
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned To</th>
                @endif
                <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
            @forelse($leads as $lead)
            <tr>
                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->status->name }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                    @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->updated_at->format('Y-m-d H:i') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm">
                    @if($lead->is_dnc)
                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">DNC</span>
                    @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-slate-600">
                    <details class="group">
                        <summary class="cursor-pointer select-none text-sky-600 hover:text-sky-500">View</summary>
                        <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
                            <p class="font-semibold text-slate-800">Phones</p>
                            @if($lead->phones->isEmpty())
                                <p class="mt-1 text-slate-500">No phone</p>
                            @else
                                <ul class="mt-1 space-y-0.5">
                                    @foreach($lead->phones as $phone)
                                        <li>{{ $phone->phone }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <p class="mt-2 font-semibold text-slate-800">Emails</p>
                            @if($lead->emails->isEmpty())
                                <p class="mt-1 text-slate-500">No email</p>
                            @else
                                <ul class="mt-1 space-y-0.5">
                                    @foreach($lead->emails as $email)
                                        <li>{{ $email->email }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </details>
                </td>
                @if($isAdmin)
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->assignedTo?->displayName() ?? '—' }}</td>
                @endif
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ route('leads.edit', $lead) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $isAdmin ? 8 : 7 }}" class="px-4 py-8 text-center text-sm text-slate-500">No leads yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($leads instanceof \Illuminate\Contracts\Pagination\Paginator && $leads->hasPages())
        <div class="border-t border-slate-200 px-4 py-3">
            {{ $leads->links() }}
        </div>
    @endif
</div>
@endsection
