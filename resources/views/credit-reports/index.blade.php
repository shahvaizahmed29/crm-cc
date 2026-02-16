@extends('layouts.app')

@section('title', 'CR Reports')

@section('content')
<x-page-header title="CR Report Panel" />

<form method="GET" action="{{ route('credit-reports.index') }}" class="mt-4 flex items-center gap-2">
    <label for="status" class="text-sm font-medium text-slate-700">Status</label>
    <select name="status" id="status" onchange="this.form.submit()" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
        <option value="recheck" {{ $status === 'recheck' ? 'selected' : '' }}>Recheck</option>
        <option value="notfound" {{ $status === 'notfound' ? 'selected' : '' }}>Not Found</option>
        <option value="sent" {{ $status === 'sent' ? 'selected' : '' }}>Sent</option>
        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
    </select>
</form>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Lead</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Phone</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Requested By</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Comment</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
            @forelse($reports as $report)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                        <a href="{{ route('leads.show', $report->lead) }}" class="text-sky-600 hover:text-sky-500">{{ $report->lead->fullName() }}</a>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $report->phone_number ?? '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ ucfirst($report->status) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $report->requestedBy?->displayName() ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $report->comment ?: '—' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <form action="{{ route('credit-reports.result', $report) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="status" class="rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    <option value="sent">Sent</option>
                                    <option value="notfound">Not Found</option>
                                </select>
                                <input type="file" name="cr_file" accept=".pdf" class="block text-xs">
                                <input type="text" name="comment" placeholder="Comment" class="rounded-md border border-slate-300 px-2 py-1 text-xs">
                                <button type="submit" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Update</button>
                            </form>
                            @if($report->report_file_path)
                                <a href="{{ route('credit-reports.download', $report) }}" class="rounded bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-500">Get Report</a>
                            @endif
                            <form action="{{ route('credit-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this CR request?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-500">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No CR requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    @if($reports->hasPages())
        <div class="border-t border-slate-200 px-4 py-3">
            {{ $reports->links() }}
        </div>
    @endif
</div>
@endsection
