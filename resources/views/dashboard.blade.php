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

@if(isset($statusChart))
@php
    $statusChartData = $statusChart->getDatasets()[0]['data'] ?? collect();
@endphp
@if($statusChartData->isNotEmpty())
<div class="mt-8">
    <h2 class="text-lg font-semibold text-slate-900">Leads by status</h2>
    <p class="mt-1 text-sm text-slate-600">Percentage of leads per status (excluding New and statuses with no leads).</p>

    @php
        $period = request('leads_chart_period', 'month');
        $periods = [
            'today' => 'This day',
            'yesterday' => 'Yesterday',
            'week' => 'This week',
            'last_7_days' => 'Last 7 days',
            'month' => 'This month',
            'year' => 'This year',
            'custom' => 'Custom',
        ];
        $customFrom = request('leads_chart_from');
        $customTo = request('leads_chart_to');
    @endphp
    <div class="mt-2 flex flex-wrap items-center gap-2">
        @foreach($periods as $value => $label)
        @if($value === 'custom')
            <span class="inline-flex items-center gap-2">
                <a href="{{ route('dashboard', ['leads_chart_period' => 'custom']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $period === 'custom' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</a>
                @if($period === 'custom')
                <form method="get" action="{{ route('dashboard') }}" class="inline-flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <input type="hidden" name="leads_chart_period" value="custom">
                    <label class="flex flex-col gap-0.5">
                        <span class="text-xs font-medium text-slate-500">From</span>
                        <input type="datetime-local" name="leads_chart_from" value="{{ $customFrom }}" class="rounded border border-slate-300 bg-white px-2 py-1 text-sm">
                    </label>
                    <label class="flex flex-col gap-0.5">
                        <span class="text-xs font-medium text-slate-500">To</span>
                        <input type="datetime-local" name="leads_chart_to" value="{{ $customTo }}" class="rounded border border-slate-300 bg-white px-2 py-1 text-sm">
                    </label>
                    <button type="submit" class="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500">Apply</button>
                </form>
                @endif
            </span>
        @else
        <a href="{{ route('dashboard', ['leads_chart_period' => $value]) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $period === $value ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</a>
        @endif
        @endforeach
    </div>

    <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="relative h-[320px] max-w-md overflow-hidden">
            {!! $statusChart->renderHtml() !!}
        </div>
    </div>
</div>
@endif
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
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned To</th>
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
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->status->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->assignedTo?->displayName() ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                            @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 text-sm text-slate-600">{{ $lead->address ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ format_in_app_tz($lead->updated_at, 'Y-m-d H:i') }}</td>
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

@if(isset($recentNotifications) && isset($topAgentsLeaderboard))
<div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Latest notifications</h2>
        <p class="mt-1 text-sm text-slate-600">Your 5 most recent notifications.</p>
        @if($recentNotifications->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <ul class="divide-y divide-slate-200 max-h-[420px] overflow-y-auto">
                @foreach($recentNotifications as $notif)
                <li class="{{ $notif->read_at ? 'bg-white' : 'bg-sky-50/70' }}">
                    <a href="{{ route('notifications.open', $notif) }}" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                        <p class="text-sm font-medium {{ $notif->read_at ? 'text-slate-600' : 'text-slate-900' }}">{{ $notif->title }}</p>
                        @if($notif->message)
                            <p class="mt-0.5 text-xs text-slate-500 line-clamp-2">{{ $notif->message }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-400">{{ format_in_app_tz($notif->notify_at, 'M j, Y g:i A') }}</p>
                    </a>
                </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-2">
                <a href="{{ route('notifications.index') }}" class="text-sm font-medium text-sky-600 hover:text-sky-500">View all notifications &rarr;</a>
            </div>
        </div>
        @else
        <p class="mt-4 rounded-xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No notifications yet.</p>
        @endif
    </div>
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Top 10 agents leaderboard</h2>
        <p class="mt-1 text-sm text-slate-600">This month — ranked by approved, then revenue.</p>
        @if($topAgentsLeaderboard->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Agent</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Approved</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Revenue</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Conv.%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($topAgentsLeaderboard as $index => $row)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-xs font-semibold text-slate-700">#{{ $index + 1 }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm font-medium text-slate-900">{{ $row['agent']->displayName() }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ $row['approved'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ $row['total'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs font-medium text-emerald-700">${{ number_format($row['revenue'], 2) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-700">{{ number_format($row['conversion_rate'], 2) }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-2">
                <a href="{{ route('reports.sales') }}" class="text-sm font-medium text-sky-600 hover:text-sky-500">Full sales report &rarr;</a>
            </div>
        </div>
        @else
        <p class="mt-4 rounded-xl bg-white p-6 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">No leaderboard data for this month yet.</p>
        @endif
    </div>
</div>
@endif

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

@if(isset($statusChart) && ($statusChart->getDatasets()[0]['data'] ?? collect())->isNotEmpty())
@push('scripts')
{!! $statusChart->renderChartJsLibrary() !!}
{!! $statusChart->renderJs() !!}
@endpush
@endif
@endsection
