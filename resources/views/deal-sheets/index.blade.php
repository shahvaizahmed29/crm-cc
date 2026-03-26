@extends('layouts.app')

@section('title', 'Deal sheets')

@section('content')
<x-page-header title="Deal sheets">
    <x-slot:actions>
        <a href="{{ route('leads.index') }}" class="inline-flex rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300">All leads</a>
    </x-slot:actions>
</x-page-header>

<p class="mt-2 text-sm text-slate-600">
    Upload a <strong>.txt</strong> file in the <strong>exact same format</strong> as when you download a lead from the admin portal
    (<span class="font-medium">Download TXT</span> on a lead, or filtered export from the Leads list). Each file creates one or more leads with status
    <span class="font-semibold">Deal sheet uploaded</span>. These leads are <strong>not</strong> shown in the agent queue; assign them here or from the lead edit screen.
</p>

@if(!$dealSheetStatusId)
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Status “Deal sheet uploaded” is missing. Run <code class="rounded bg-amber-100 px-1">php artisan migrate</code> (and seeders if needed).
    </div>
@endif

<div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-slate-900">Upload deal sheet (.txt)</h2>
    <form action="{{ route('deal-sheets.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
        @csrf
        <div class="min-w-0 flex-1">
            <label for="deal_sheet" class="mb-1 block text-sm font-medium text-slate-700">TXT file</label>
            <input
                type="file"
                name="deal_sheet"
                id="deal_sheet"
                accept=".txt,text/plain"
                required
                class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sky-700"
            >
            @error('deal_sheet')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="inline-flex justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 disabled:opacity-50" @if(!$dealSheetStatusId) disabled @endif>
            Import
        </button>
    </form>
</div>

<div class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-800">Deal sheet leads</h2>
        <p class="mt-0.5 text-xs text-slate-500">Assign to an agent using the dropdown, or open the lead to edit like any other lead.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assign to</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($leads as $lead)
                    <tr>
                        <td class="align-middle whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $lead->id }}</td>
                        <td class="align-middle px-4 py-3 text-sm">
                            <a href="{{ route('leads.edit', $lead) }}" class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                        </td>
                        <td class="align-middle whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ format_in_app_tz($lead->created_at, 'Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 align-middle">
                            <form action="{{ route('deal-sheets.assign', $lead) }}" method="POST" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="assigned_to" class="h-9 min-w-[10rem] rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    <option value="">— Unassigned —</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ (int) $lead->assigned_to === (int) $agent->id ? 'selected' : '' }}>{{ $agent->displayName() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="inline-flex h-9 shrink-0 items-center rounded-md bg-slate-800 px-3 text-xs font-semibold text-white hover:bg-slate-700">Save</button>
                            </form>
                            @error('assigned_to')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-middle">
                            <a href="{{ route('leads.edit', $lead) }}" class="inline-flex h-9 items-center justify-end text-sm font-medium text-sky-600 hover:text-sky-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No deal sheet leads yet. Upload a TXT file above.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads->hasPages())
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
            {{ $leads->links() }}
        </div>
    @endif
</div>
@endsection
