@extends('layouts.app')

@section('title', 'Sales Report')

@section('content')
<x-page-header title="Sales Report" />
<p class="mt-1 text-sm text-slate-600">Monthly performance summary by agent.</p>

<form method="GET" action="{{ route('reports.sales') }}" class="mt-4 flex items-center gap-2">
    <label for="month" class="text-sm font-medium text-slate-700">Month</label>
    <input
        type="month"
        id="month"
        name="month"
        value="{{ $monthValue }}"
        onchange="this.form.submit()"
        class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
    >
    <span class="text-sm text-slate-500">{{ $monthLabel }}</span>
</form>

<div class="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Agent Leaderboard</h2>
        <p class="text-xs text-slate-500">Ranked by Approved, then Revenue</p>
    </div>

    @if($leaderboard->isEmpty())
        <p class="mt-3 text-sm text-slate-500">No leaderboard data for {{ $monthLabel }}.</p>
    @else
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($leaderboard->take(3) as $index => $row)
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rank #{{ $index + 1 }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $row['agent']->displayName() }}</p>
                    <p class="mt-1 text-xs text-slate-600">
                        Approved: <span class="font-semibold text-slate-900">{{ $row['approved'] }}</span> |
                        Total: <span class="font-semibold text-slate-900">{{ $row['total'] }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-600">
                        Revenue: <span class="font-semibold text-emerald-700">${{ number_format($row['revenue'], 2) }}</span> |
                        Conversion: <span class="font-semibold text-slate-900">{{ number_format($row['conversion_rate'], 2) }}%</span>
                    </p>
                </div>
            @endforeach
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Rank</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Agent</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Approved</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Revenue</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Conversion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($leaderboard as $index => $row)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">#{{ $index + 1 }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ $row['agent']->displayName() }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ $row['approved'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ $row['total'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-emerald-700">${{ number_format($row['revenue'], 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ number_format($row['conversion_rate'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">#</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Employee Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Username</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Approved</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Cancel</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Pending</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Not Qualified</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total Revenue</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
            @forelse($agents as $index => $agent)
                @php
                    $approved = (int) $agent->approved_count;
                    $cancel = (int) $agent->cancel_count;
                    $pending = (int) $agent->pending_count;
                    $notQualified = (int) $agent->not_qualified_count;
                    $total = $approved + $cancel + $pending + $notQualified;
                    $revenue = (float) ($agent->approved_revenue_sum ?? 0);
                @endphp
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $index + 1 }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $agent->displayName() }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $agent->username ?: '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $approved }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $cancel }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $pending }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $notQualified }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ $total }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-emerald-700">${{ number_format($revenue, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No agent data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
