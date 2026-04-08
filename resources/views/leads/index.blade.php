@extends('layouts.app')

@section('title', 'Leads')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $isAgent = auth()->user()->isAgent();
    $isSubAgent = auth()->user()->isSubAgent();
    $isStaffAgent = auth()->user()->isStaffAgent();
    $sort = $sort ?? request('sort', 'updated_at');
    $order = $order ?? request('order', 'desc');
    $sortUrl = function ($col) use ($sort, $order) {
        $next = ($sort === $col) ? ($order === 'desc' ? 'asc' : 'desc') : 'asc';
        return route('leads.index', array_merge(request()->query(), ['sort' => $col, 'order' => $next]));
    };
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
    @if($isAdmin)
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('leads.export.txt', request()->query()) }}" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Download TXT</a>
            <a href="{{ route('leads.export') }}" class="inline-flex justify-center rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-500">Download CSV</a>
        </div>
    @endif
</div>

<form method="GET" action="{{ route('leads.index') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3">
        <h2 class="text-sm font-semibold text-slate-900">Filter Leads</h2>
        <p class="text-xs text-slate-500">Use date range and optional filters to narrow results.</p>
    </div>

    {{-- Exactly 2 rows: row1 = Keyword, Status, DNC | row2 = Assigned To, From Date, To Date --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="min-w-0">
            <label for="keyword" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Keyword</label>
            <input
                type="text"
                id="keyword"
                name="keyword"
                value="{{ request('keyword') }}"
                placeholder="First name, last name, or phone"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
        </div>
        @if($isAdmin || ($isAgent && $statuses->isNotEmpty()))
            <div class="min-w-0">
                <label for="status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Status</label>
                <select name="status" id="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->id }}" {{ request('status') == (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div></div>
        @endif
        @if($isAdmin)
            <div class="min-w-0">
                <label for="dnc" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">DNC</label>
                <select name="dnc" id="dnc" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All</option>
                    <option value="1" {{ request('dnc') === '1' ? 'selected' : '' }}>DNC only</option>
                    <option value="0" {{ request('dnc') === '0' ? 'selected' : '' }}>Non-DNC</option>
                </select>
            </div>
        @else
            <div></div>
        @endif

        @if($isAdmin)
            <div class="min-w-0">
                <label for="assigned_to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Assigned To</label>
                <select name="assigned_to" id="assigned_to" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="all" {{ (request('assigned_to') === null || request('assigned_to') === 'all') ? 'selected' : '' }}>All</option>
                    <option value="" {{ request('assigned_to') === '' ? 'selected' : '' }}>Unassigned</option>
                    @foreach($assignableUsers ?? [] as $agent)
                        <option value="{{ $agent->id }}" {{ request('assigned_to') == (string) $agent->id ? 'selected' : '' }}>{{ $agent->displayName() }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div></div>
        @endif
        <div class="min-w-0">
            <label for="date_from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">From Date</label>
            <input
                type="date"
                id="date_from"
                name="date_from"
                value="{{ request('date_from') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
        </div>
        <div class="min-w-0">
            <label for="date_to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">To Date</label>
            <input
                type="date"
                id="date_to"
                name="date_to"
                value="{{ request('date_to') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ request('sort', 'updated_at') }}">
    <input type="hidden" name="order" value="{{ request('order', 'desc') }}">
    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Apply Filters</button>
        <a href="{{ route('leads.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
</form>

@if($isStaffAgent && isset($holdingCount))
<div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
    <p class="text-sm text-slate-700">
        Holding statuses count:
        <span class="font-semibold">{{ $holdingCount }}</span> / {{ $historyLimit }}
    </p>
    <p class="mt-1 text-xs text-slate-500">
        These are your leads in holding statuses: {{ $statuses->isNotEmpty() ? $statuses->pluck('name')->join(', ') : '—' }}.
        @if(isset($enforcedHistoryLimit) && $holdingCount >= $enforcedHistoryLimit)
            @if($isAgent)
                Queue is locked. Update one lead to a non-holding status to unlock new lead assignment.
            @elseif($isSubAgent)
                New deal-sheet assignments are blocked. Update one lead to a non-holding status to unlock assignments.
            @endif
        @endif
    </p>
</div>
@endif

@php
    $storageKey = 'leads_table_columns' . ($isAdmin ? '_admin' : '');
@endphp
<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200" x-data="{
    availableColumns: @js($availableColumns),
    defaultColumns: @js($defaultColumns),
    storageKey: @js($storageKey),
    selectedColumns: [],
    open: false,
    init() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            this.selectedColumns = saved ? JSON.parse(saved) : this.defaultColumns.slice();
        } catch (e) {
            this.selectedColumns = this.defaultColumns.slice();
        }
        if (!Array.isArray(this.selectedColumns) || this.selectedColumns.length === 0) {
            this.selectedColumns = this.defaultColumns.slice();
        }
    },
    toggle(colId) {
        const i = this.selectedColumns.indexOf(colId);
        if (i === -1) this.selectedColumns.push(colId);
        else this.selectedColumns.splice(i, 1);
        this.save();
    },
    isVisible(colId) {
        return this.selectedColumns.indexOf(colId) !== -1;
    },
    save() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.selectedColumns));
        } catch (e) {}
    },
    resetColumns() {
        this.selectedColumns = this.defaultColumns.slice();
        this.save();
    }
}">
    <div class="flex items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2">
        <span class="text-sm font-medium text-slate-600">Leads table</span>
        <div class="relative">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <i class="fa-solid fa-columns text-slate-500"></i> Columns
            </button>
            <div x-show="open" x-cloak @click.outside="open = false"
                 class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-2 shadow-lg ring-1 ring-black/5">
                <div class="px-3 py-1.5 text-xs font-semibold text-slate-500">Show columns</div>
                <template x-for="col in availableColumns" :key="col.id">
                    <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 hover:bg-slate-50">
                        <input type="checkbox" :checked="isVisible(col.id)" @change="toggle(col.id)" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span class="text-sm text-slate-700" x-text="col.label"></span>
                    </label>
                </template>
                <div class="mt-2 border-t border-slate-100 pt-2 px-3">
                    <button type="button" @click="resetColumns(); open = false" class="text-xs font-medium text-sky-600 hover:text-sky-500">Reset to default</button>
                </div>
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th x-show="isVisible('name')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="name">Name</th>
                    <th x-show="isVisible('status')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="status">Status</th>
                    <th x-show="isVisible('total_debt')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="total_debt">
                        <a href="{{ $sortUrl('approx_debt') }}" class="inline-flex items-center gap-0.5 group">
                            Total Debt
                            @if($sort === 'approx_debt')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th x-show="isVisible('fees')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="fees">
                        <a href="{{ $sortUrl('fees') }}" class="inline-flex items-center gap-0.5 group">
                            Fees
                            @if($sort === 'fees')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th x-show="isVisible('last_update')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="last_update">
                        <a href="{{ $sortUrl('updated_at') }}" class="inline-flex items-center gap-0.5 group">
                            Last Update
                            @if($sort === 'updated_at')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th x-show="isVisible('dnc')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="dnc">DNC</th>
                    <th x-show="isVisible('contacts')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="contacts">Contacts</th>
                    @if($isAdmin)
                    <th x-show="isVisible('assigned_to')" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600" data-column="assigned_to">Assigned To</th>
                    @endif
                    <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($leads as $lead)
                <tr>
                    <td x-show="isVisible('name')" class="whitespace-nowrap px-4 py-3" data-column="name">
                        <a href="{{ route('leads.show', $lead) }}" class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                    </td>
                    <td x-show="isVisible('status')" class="whitespace-nowrap px-4 py-3 text-sm text-slate-600" data-column="status">{{ $lead->status->name }}</td>
                    <td x-show="isVisible('total_debt')" class="whitespace-nowrap px-4 py-3 text-sm text-slate-600" data-column="total_debt">
                        @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                    </td>
                    <td x-show="isVisible('fees')" class="whitespace-nowrap px-4 py-3 text-sm text-slate-600" data-column="fees">
                        @if($lead->fees) ${{ number_format($lead->fees, 2) }} @else — @endif
                    </td>
                    <td x-show="isVisible('last_update')" class="whitespace-nowrap px-4 py-3 text-sm text-slate-600" data-column="last_update">{{ format_in_app_tz($lead->updated_at, 'Y-m-d H:i') }}</td>
                    <td x-show="isVisible('dnc')" class="whitespace-nowrap px-4 py-3 text-sm" data-column="dnc">
                        @if($lead->is_dnc)
                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">DNC</span>
                        @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                        @endif
                    </td>
                    <td x-show="isVisible('contacts')" class="px-4 py-3 text-sm text-slate-600" data-column="contacts">
                        {{-- Eye trigger --}}
                        <!-- <button type="button"
                            class="inline-flex items-center gap-1 cursor-pointer select-none text-sky-600 hover:text-sky-500"
                            data-modal-open="lead-modal-{{ $lead->id }}">
                            <i class="fa-solid fa-eye"></i> <span>View</span>
                        </button> -->
                        <button type="button" class="w-6 h-6 flex items-center justify-center text-sky-500 hover:text-sky-600 text-xs rounded-full transition-all cursor-pointer" data-modal-open="lead-modal-{{ $lead->id }}">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        {{-- Modal --}}
                        <div id="lead-modal-{{ $lead->id }}"
                            class="fixed inset-0 z-50 hidden"
                            aria-hidden="true">

                            {{-- Backdrop --}}
                            <div class="absolute inset-0 bg-slate-900/40 opacity-0 transition-opacity duration-150"
                                data-modal-backdrop></div>

                            {{-- Panel --}}
                            <div class="relative mx-auto mt-24 w-[92%] max-w-md rounded-lg bg-white shadow-lg
                opacity-0 scale-95 transition duration-150"
                                data-modal-panel>

                                {{-- Header --}}
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-eye text-slate-500"></i>
                                        <p class="text-sm font-semibold text-slate-800">Lead details</p>
                                    </div>

                                    <!-- <button type="button"
                                        class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                        data-modal-close
                                        aria-label="Close">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button> -->
                                    <button type="button" class="w-6 h-6 flex items-center justify-center text-red-500 hover:text-red-600 bg-slate-100 hover:bg-slate-200 text-xs rounded-full transition-all cursor-pointer" data-modal-close>
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                {{-- Body (your original UI) --}}
                                <div class="p-3 text-sm">
                                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">

                                        {{-- Phones --}}
                                        <div class="flex items-center gap-2 bg-slate-50 px-3 py-2">
                                            <i class="fa-solid fa-phone text-slate-500 text-xs"></i>
                                            <span class="text-xs font-semibold text-slate-700">Phones</span>
                                        </div>

                                        @if($lead->phones->isEmpty())
                                        <div class="px-3 py-2 text-xs text-slate-500">No phone</div>
                                        @else
                                        <div class="divide-y divide-slate-100">
                                            @foreach($lead->phones as $phone)
                                            <div class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700">
                                                <i class="fa-solid fa-minus text-[10px] text-slate-300"></i>
                                                <span>{{ $phone->phone }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                        @endif

                                        {{-- Divider between sections --}}
                                        <div class="h-px bg-slate-200"></div>

                                        {{-- Emails --}}
                                        <div class="flex items-center gap-2 bg-slate-50 px-3 py-2">
                                            <i class="fa-solid fa-envelope text-slate-500 text-xs"></i>
                                            <span class="text-xs font-semibold text-slate-700">Emails</span>
                                        </div>

                                        @if($lead->emails->isEmpty())
                                        <div class="px-3 py-2 text-xs text-slate-500">No email</div>
                                        @else
                                        <div class="divide-y divide-slate-100">
                                            @foreach($lead->emails as $email)
                                            <div class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700">
                                                <i class="fa-solid fa-minus text-[10px] text-slate-300"></i>
                                                <span class="truncate">{{ $email->email }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                        @endif

                                    </div>
                                </div>

                                {{-- Footer --}}
                                <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                                    <button type="button"
                                        class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        data-modal-close>
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>

                    </td>
                    @if($isAdmin)
                    <td x-show="isVisible('assigned_to')" class="whitespace-nowrap px-4 py-3 text-sm text-slate-600" data-column="assigned_to">{{ $lead->assignedTo?->displayName() ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($isAdmin)
                                <a href="{{ route('leads.download.txt', $lead) }}" class="w-6 h-6 flex items-center justify-center text-indigo-500 hover:text-indigo-600 text-xs rounded-full transition-all cursor-pointer" title="Download TXT">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <form action="{{ route('leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('Soft delete this lead?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-6 h-6 flex items-center justify-center text-red-500 hover:text-red-600 text-xs rounded-full transition-all cursor-pointer" title="Delete Lead">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('leads.edit', $lead) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer" title="Edit">
                                <i class="fa-solid fa-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="20" class="px-4 py-8 text-center text-sm text-slate-500">No leads yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="border-t border-slate-200 px-4 py-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
            </p>
            @if($leads->lastPage() > 1)
            <div class="flex items-center gap-1">
                @if($leads->onFirstPage())
                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm text-slate-400">Previous</span>
                @else
                    <a href="{{ $leads->previousPageUrl() }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Previous</a>
                @endif

                @foreach($leads->getUrlRange(max(1, $leads->currentPage() - 2), min($leads->lastPage(), $leads->currentPage() + 2)) as $page => $url)
                    @if($page === $leads->currentPage())
                        <span class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-sm font-semibold text-sky-700">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">{{ $page }}</a>
                    @endif
                @endforeach

                @if($leads->hasMorePages())
                    <a href="{{ $leads->nextPageUrl() }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Next</a>
                @else
                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm text-slate-400">Next</span>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
<script>
(() => {
  function openModal(id) {
    const root = document.getElementById(id);
    if (!root) return;

    const backdrop = root.querySelector('[data-modal-backdrop]');
    const panel = root.querySelector('[data-modal-panel]');

    root.classList.remove('hidden');
    root.setAttribute('aria-hidden', 'false');

    requestAnimationFrame(() => {
      backdrop.classList.remove('opacity-0');
      panel.classList.remove('opacity-0', 'scale-95');
      backdrop.classList.add('opacity-100');
      panel.classList.add('opacity-100', 'scale-100');
    });
  }

  function closeModal(root) {
    if (!root) return;

    const backdrop = root.querySelector('[data-modal-backdrop]');
    const panel = root.querySelector('[data-modal-panel]');

    backdrop.classList.remove('opacity-100');
    panel.classList.remove('opacity-100', 'scale-100');
    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');

    setTimeout(() => {
      root.classList.add('hidden');
      root.setAttribute('aria-hidden', 'true');
    }, 160);
  }

  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-modal-open]');
    if (openBtn) return openModal(openBtn.dataset.modalOpen);

    const closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) return closeModal(closeBtn.closest('div[id^="lead-modal-"]'));

    const backdrop = e.target.closest('[data-modal-backdrop]');
    if (backdrop) return closeModal(backdrop.closest('div[id^="lead-modal-"]'));
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('div[id^="lead-modal-"]:not(.hidden)').forEach(closeModal);
  });
})();
</script>
@endsection