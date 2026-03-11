@extends('layouts.app')

@section('title', 'Agent History')

@section('content')
<x-page-header title="Lead History (Holding Statuses)" />

<p class="mt-1 text-sm text-slate-600">
    These are your leads in holding statuses: Need To Reconnect, Callback, Payoff, Maxout, Drop.
</p>

<div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
    <p class="text-sm text-slate-700">
        Holding statuses count:
        <span class="font-semibold">{{ $holdingCount }}</span> / {{ $historyLimit }}
    </p>
</div>

<div class="mt-6 overflow-hidden rounded-lg bg-white shadow ring-1 ring-slate-200">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Updated</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
            @forelse($leads as $lead)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $lead->fullName() }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $lead->status->name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ format_in_app_tz($lead->updated_at, 'Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <!-- <a href="{{ route('leads.edit', $lead) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">
                            Update Status
                        </a> -->
                        <a href="{{ route('leads.edit', $lead) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                        No holding-status leads found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
