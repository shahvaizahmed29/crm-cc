@extends('layouts.app')

@section('title', 'New Leads')

@section('content')
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">New Leads</h1>
        <p class="mt-1 text-sm text-slate-600">All leads currently in New status.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('leads.import.sample') }}" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Download Sample CSV</a>
        <a href="{{ route('leads.import.form') }}" class="inline-flex justify-center rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-500">Import CSV</a>
        <a href="{{ route('leads.create') }}" class="inline-flex justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Add Lead</a>
    </div>
</div>

<div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <form action="{{ route('leads.new.history-limit.update') }}" method="POST" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        @csrf
        <div>
            <label for="agent_history_limit" class="block text-sm font-medium text-slate-700">Agent history limit</label>
            <p class="mt-1 text-xs text-slate-500">Agents are blocked from taking new leads when holding statuses reach this limit.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                type="number"
                name="agent_history_limit"
                id="agent_history_limit"
                min="1"
                max="500"
                value="{{ old('agent_history_limit', $historyLimit) }}"
                class="w-28 rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                required
            >
            <button type="submit" class="inline-flex justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Update Limit</button>
        </div>
    </form>
    @error('agent_history_limit')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">SSN / Phone</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total Debt</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Last Update</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">DNC</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Contacts</th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned To</th>
                <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
            @forelse($leads as $lead)
            <tr>
                <td class="whitespace-nowrap px-4 py-3">
                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                    {{ $lead->ssn ?: ($lead->phones->first()?->phone ?: '—') }}
                </td>
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
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->assignedTo?->displayName() ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ route('leads.edit', $lead) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No new leads found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($leads->hasPages())
        <div class="border-t border-slate-200 px-4 py-3">
            {{ $leads->links() }}
        </div>
    @endif
</div>
@endsection

