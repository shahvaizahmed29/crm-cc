@extends('layouts.app')

@section('title', 'New Leads')

@section('content')
@php
    $sort = $sort ?? request('sort', 'updated_at');
    $order = $order ?? request('order', 'desc');
    $sortUrl = function ($col) use ($sort, $order) {
        $next = ($sort === $col) ? ($order === 'desc' ? 'asc' : 'desc') : 'asc';
        return route('leads.new.index', array_merge(request()->query(), ['sort' => $col, 'order' => $next]));
    };
@endphp
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">New Leads</h1>
        <p class="mt-1 text-sm text-slate-600">Leads with status New or not assigned to any user.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('leads.import.sample') }}"
            class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Download
            Sample CSV</a>
        <a href="{{ route('leads.import.form') }}"
            class="inline-flex justify-center rounded-md bg-slate-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-500">Import
            CSV</a>
        <a href="{{ route('leads.create') }}"
            class="inline-flex justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Add
            Lead</a>
    </div>
</div>

<form method="GET" action="{{ route('leads.new.index') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3">
        <h2 class="text-sm font-semibold text-slate-900">Filter New Leads</h2>
        <p class="text-xs text-slate-500">Use date range and optional filters to narrow results.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="keyword" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Keyword</label>
            <input
                type="text"
                id="keyword"
                name="keyword"
                value="{{ request('keyword') }}"
                placeholder="First name, last name, or phone"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
        </div>
        <div>
            <label for="dnc" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">DNC</label>
            <select name="dnc" id="dnc" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                <option value="">All</option>
                <option value="1" {{ request('dnc') === '1' ? 'selected' : '' }}>DNC only</option>
                <option value="0" {{ request('dnc') === '0' ? 'selected' : '' }}>Non-DNC</option>
            </select>
        </div>
        <div>
            <label for="date_from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">From Date</label>
            <input
                type="date"
                id="date_from"
                name="date_from"
                value="{{ request('date_from') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
        </div>
        <div>
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
        <a href="{{ route('leads.new.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
</form>

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">SSN</th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Phone</th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                        <a href="{{ $sortUrl('approx_debt') }}" class="inline-flex items-center gap-0.5 group">
                            Total Debt
                            @if($sort === 'approx_debt')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                        <a href="{{ $sortUrl('updated_at') }}" class="inline-flex items-center gap-0.5 group">
                            Last Update
                            @if($sort === 'updated_at')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                        <a href="{{ $sortUrl('fees') }}" class="inline-flex items-center gap-0.5 group">
                            Fees
                            @if($sort === 'fees')
                                <span class="text-sky-600">{{ $order === 'desc' ? '↓' : '↑' }}</span>
                            @else
                                <span class="text-slate-300 group-hover:text-slate-500">↕</span>
                            @endif
                        </a>
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">DNC</th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Contacts
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned
                        To</th>
                    <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($leads as $lead)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('leads.show', $lead) }}"
                            class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $lead->ssn ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $lead->phones->first()?->phone ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ format_in_app_tz($lead->updated_at, 'Y-m-d H:i') }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        @if($lead->fees) ${{ number_format($lead->fees, 2) }} @else — @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                        @if($lead->is_dnc)
                        <span
                            class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">DNC</span>
                        @else
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        <!-- <button type="button"
                                        class="inline-flex items-center justify-center rounded p-1 text-slate-500 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
                                        data-modal-open="lead-modal-{{ $lead->id }}">
                                        <i class="fa-solid fa-eye"></i>
                                    </button> -->
                        <button type="button" class="w-6 h-6 flex items-center justify-center text-sky-500 hover:text-sky-600 text-xs rounded-full transition-all cursor-pointer" data-modal-open="lead-modal-{{ $lead->id }}">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <div id="lead-modal-{{ $lead->id }}" class="fixed inset-0 z-50 hidden" aria-hidden="true">
                            <div class="absolute inset-0 bg-slate-900/40 opacity-0 transition-opacity duration-150"
                                data-modal-backdrop></div>

                            <div class="relative mx-auto mt-24 w-[92%] max-w-lg rounded-lg bg-white shadow-lg
                          opacity-0 scale-95 transition duration-150" data-modal-panel>
                                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <p class="font-semibold text-slate-800">Lead details</p>
                                    <button type="button" class="w-6 h-6 flex items-center justify-center text-red-500 hover:text-red-600 bg-slate-100 hover:bg-slate-200 text-xs rounded-full transition-all cursor-pointer" data-modal-close>
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

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

                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $lead->assignedTo?->displayName() ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <!-- <a href="{{ route('leads.edit', $lead) }}"
                                        class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a> -->
                        <a href="{{ route('leads.edit', $lead) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">No new leads found.</td>
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
                backdrop.classList.add('opacity-100');
                panel.classList.add('opacity-100', 'scale-100');
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95');
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
            if (closeBtn) return closeModal(closeBtn.closest('[id^="lead-modal-"]'));

            const backdrop = e.target.closest('[data-modal-backdrop]');
            if (backdrop) return closeModal(backdrop.closest('[id^="lead-modal-"]'));
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('[id^="lead-modal-"]:not(.hidden)').forEach(closeModal);
        });
    })();
</script>

@endsection