@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div>
    <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-600">Overview of your leads and activity.</p>
</div>

<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 {{ isset($newLeadsCount) ? 'xl:grid-cols-5' : '' }}">
    @if(isset($newLeadsCount))
    <a href="{{ route('leads.new.index') }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-amber-400 hover:shadow-md">
        <p class="text-sm font-medium text-slate-500">New Leads</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($newLeadsCount) }}</p>
        <p class="mt-1 text-xs text-sky-600 font-medium">View all &rarr;</p>
    </a>
    @endif
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium text-slate-500">Monthly Leads</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($stats['monthly_leads']) }}</p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium text-slate-500">Daily Leads</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($stats['daily_leads']) }}</p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium text-slate-500">Weekly Leads</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($stats['weekly_leads']) }}</p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium text-slate-500">Last Month</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($stats['last_month']) }}</p>
    </div>
</div>

@if(isset($leadsCountByStatus) && count($leadsCountByStatus) > 0)
<div class="mt-8">
    <h2 class="text-lg font-semibold text-slate-900">Leads by status (submitted by you)</h2>
    <p class="mt-1 text-sm text-slate-600">Count of leads you have in each status. No link to leads—counts only.</p>
    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">Count</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach($leadsCountByStatus as $row)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ $row['name'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-600">{{ number_format($row['count']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="mt-8">
    <h2 class="text-lg font-semibold text-slate-900">Recent Activities</h2>
    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        @if($recentLeads->isEmpty())
            <p class="p-6 text-sm text-slate-500">No leads yet.</p>
        @else
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total Debt</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Address</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Last Update</th>
                        <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($recentLeads as $lead)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $lead->fullName() }}</span>
                            @if($lead->status->slug === 'new')
                                <span class="ml-2 inline-flex rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">New</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                            @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 text-sm text-slate-600">{{ $lead->address ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <!-- <a href="{{ route('leads.edit', $lead) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a> -->
                            <a href="{{ route('leads.edit', $lead) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
    @if($recentLeads->isNotEmpty())
        <p class="mt-2 text-sm text-slate-500">
            <a href="{{ route('leads.index') }}" class="font-medium text-sky-600 hover:text-sky-500">View all leads &rarr;</a>
        </p>
    @endif
</div>
@endsection
