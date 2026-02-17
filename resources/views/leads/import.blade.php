@extends('layouts.app')

@section('title', 'Import Leads')

@section('content')
<x-page-header title="Import Leads (CSV/XLSX)" :back-url="route('leads.index')" back-text="Back to list" />

<p class="mt-1 text-sm text-slate-600">Upload a CSV/XLSX file. The first row should be headers. Supported template columns: <strong>F name</strong>, <strong>M name</strong>, <strong>L name</strong>, <strong>Address</strong>, <strong>city</strong>, <strong>state</strong>, <strong>zip</strong>, <strong>ssn</strong>, <strong>Dob</strong>, <strong>Debt</strong>, <strong>phone1</strong>, <strong>phone2</strong>, <strong>phone3</strong>.</p>

@if(session('import_errors'))
    <div class="mt-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800 border border-amber-200">
        <p class="font-medium">Some rows could not be imported:</p>
        <ul class="mt-2 list-disc list-inside space-y-1 max-h-40 overflow-y-auto">
            @foreach(array_slice(session('import_errors', []), 0, 20) as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
        @if(count(session('import_errors', [])) > 20)
            <p class="mt-2 text-amber-700">… and {{ count(session('import_errors')) - 20 }} more.</p>
        @endif
    </div>
@endif

<form action="{{ route('leads.import') }}" method="POST" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-4">
    @csrf
    <x-form-field label="CSV/XLSX file" for="file" :required="true">
        <input type="file" name="file" id="file" accept=".csv,.txt,.xlsx" required
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sky-700">
    </x-form-field>
    <x-form-field label="Default status (when not in CSV)" for="default_status_id" :required="true">
        <x-select name="default_status_id" id="default_status_id" :options="$statuses->pluck('name', 'id')" :selected="old('default_status_id', $defaultStatusId)" required />
    </x-form-field>
    <div>
        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Import</button>
    </div>
</form>

<div class="mt-8 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200">
    <p class="font-medium">Example header row (CSV/XLSX):</p>
    <code class="mt-1 block break-all">F name,M name,L name,Address,city,state,zip,ssn,Dob,Debt,phone1,phone2,phone3</code>
</div>

<div class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-800">Upload History</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Uploaded At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">File</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Uploaded By</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Default Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Rows</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($importHistories as $history)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $history->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $history->original_file_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $history->uploadedBy?->displayName() ?? 'System' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $history->defaultStatus?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                            Total: {{ $history->total_rows }} |
                            Uploaded: {{ $history->created_rows }} |
                            Skipped: {{ $history->skipped_rows }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    href="{{ route('leads.import.history.download', [$history, 'original']) }}"
                                    class="inline-flex rounded bg-slate-600 px-2 py-1 text-xs font-medium text-white hover:bg-slate-500"
                                >
                                    Original File
                                </a>
                                @if($history->failed_rows > 0 && $history->failed_rows_file_path)
                                    <a
                                        href="{{ route('leads.import.history.download', [$history, 'failed']) }}"
                                        class="inline-flex rounded bg-amber-600 px-2 py-1 text-xs font-medium text-white hover:bg-amber-500"
                                    >
                                        Failed Rows ({{ $history->failed_rows }})
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No upload history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
