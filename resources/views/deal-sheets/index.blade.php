@extends('layouts.app')

@section('title', 'Deal sheets')

@section('content')
<x-page-header title="Deal sheets">
    <x-slot:actions>
        <a href="{{ route('leads.index') }}" class="inline-flex rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300">All leads</a>
        @if(isset($newStatusId) && $newStatusId)
            <a href="{{ route('leads.new.index') }}" class="inline-flex rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300">New leads</a>
        @endif
    </x-slot:actions>
</x-page-header>

@if(session('import_warnings'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-medium">Some files were skipped:</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach(session('import_warnings') as $w)
                <li>{{ $w }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p class="mt-2 text-sm text-slate-600">
    Upload one or more <strong>.txt</strong> files in the <strong>same format</strong> as the admin lead export
    (<span class="font-medium">Download TXT</span> on a lead, or filtered export from the Leads list). Choose whether imported rows become
    <strong>New</strong> (agent queue / New Leads) or <strong>Deal sheet uploaded</strong> (not in the queue; assign below or on the lead).
</p>

@if(!$dealSheetStatusId)
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Status “Deal sheet uploaded” is missing. You can still import as <strong>New</strong>. For “Deal sheet uploaded”, run <code class="rounded bg-amber-100 px-1">php artisan migrate</code>.
    </div>
@endif
@if(!isset($newStatusId) || !$newStatusId)
    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Status “New” is missing. Run migrations and seeders before importing as New.
    </div>
@endif

<div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-slate-900">Bulk upload (.txt)</h2>
    <p class="mt-1 text-xs text-slate-500">Select multiple files at once (Ctrl/Cmd+click). Each file can contain one lead or several separated by a line of 70 dashes (same as bulk TXT export).</p>

    <form action="{{ route('deal-sheets.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
        @csrf

        <div>
            <span class="mb-2 block text-sm font-medium text-slate-700">Import leads as</span>
            <div class="flex flex-col gap-2 sm:flex-row sm:gap-6">
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <input type="radio" name="import_status" value="new" class="text-sky-600 focus:ring-sky-500" {{ old('import_status', 'deal-sheet-uploaded') === 'new' ? 'checked' : '' }}>
                    <span class="text-sm text-slate-800"><strong>New</strong> — appears on New Leads &amp; agent queue</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <input type="radio" name="import_status" value="deal-sheet-uploaded" class="text-sky-600 focus:ring-sky-500" {{ old('import_status', 'deal-sheet-uploaded') === 'deal-sheet-uploaded' ? 'checked' : '' }}>
                    <span class="text-sm text-slate-800"><strong>Deal sheet uploaded</strong> — not in queue; admin assigns</span>
                </label>
            </div>
            @error('import_status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="deal_sheets" class="mb-1 block text-sm font-medium text-slate-700">TXT file(s)</label>
            <input
                type="file"
                name="deal_sheets[]"
                id="deal_sheets"
                accept=".txt,text/plain"
                multiple
                required
                class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sky-700"
            >
            @error('deal_sheets')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @foreach($errors->getMessages() as $key => $messages)
                @if(\Illuminate\Support\Str::startsWith($key, 'deal_sheets.'))
                    @foreach($messages as $message)
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @endforeach
                @endif
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50">
                Preview Import
            </button>
        </div>
    </form>
</div>

@if(isset($preview) && is_array($preview))
<div class="mt-6 rounded-xl border border-sky-200 bg-sky-50 p-6 shadow-sm">
    <h2 class="text-base font-semibold text-slate-900">Preview Before Import</h2>
    <p class="mt-1 text-sm text-slate-700">
        Parsed <strong>{{ $preview['total_leads'] ?? 0 }}</strong> lead(s) from
        <strong>{{ $preview['files_prepared'] ?? 0 }}</strong> file(s),
        target status:
        <strong>{{ (($preview['import_status'] ?? '') === 'new') ? 'New' : 'Deal sheet uploaded' }}</strong>.
    </p>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">File</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Leads parsed</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-600">Example names</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach(($preview['files'] ?? []) as $f)
                    <tr>
                        <td class="px-3 py-2 text-sm text-slate-800">{{ $f['original_name'] ?? 'file.txt' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">{{ $f['lead_count'] ?? 0 }}</td>
                        <td class="px-3 py-2 text-sm text-slate-700">
                            @php
                                $examples = collect($f['blocks'] ?? [])
                                    ->take(3)
                                    ->map(fn($b) => trim((string) (($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''))))
                                    ->filter()
                                    ->values();
                            @endphp
                            {{ $examples->isNotEmpty() ? $examples->join(', ') : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <form action="{{ route('deal-sheets.import-preview') }}" method="POST">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $previewToken }}">
            <button type="submit" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                Confirm Import
            </button>
        </form>
        <a href="{{ route('deal-sheets.index') }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Cancel Preview
        </a>
    </div>
</div>
@endif

<div class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-800">Deal sheet uploaded — leads</h2>
        <p class="mt-0.5 text-xs text-slate-500">Only leads with status “Deal sheet uploaded”. Imports as <strong>New</strong> appear on the <a href="{{ route('leads.new.index') }}" class="font-medium text-sky-600 hover:text-sky-500">New Leads</a> page.</p>
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
                                <select name="assigned_to" class="h-9 min-w-40 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
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
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No leads with status “Deal sheet uploaded” yet. Choose that option above or see <a href="{{ route('leads.new.index') }}" class="text-sky-600 hover:text-sky-500">New Leads</a> for queue imports.</td>
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
