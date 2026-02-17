@extends('layouts.app')

@section('title', 'Leads')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $isAgent = auth()->user()->isAgent();
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
</div>

<form method="GET" action="{{ route('leads.index') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3">
        <h2 class="text-sm font-semibold text-slate-900">Filter Leads</h2>
        <p class="text-xs text-slate-500">Use date range and optional filters to narrow results.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <div class="xl:col-span-2">
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

        @if($isAdmin || ($isAgent && $statuses->isNotEmpty()))
            <div>
                <label for="status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Status</label>
                <select name="status" id="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->id }}" {{ request('status') == (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($isAdmin)
            <div>
                <label for="dnc" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">DNC</label>
                <select name="dnc" id="dnc" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                    <option value="">All</option>
                    <option value="1" {{ request('dnc') === '1' ? 'selected' : '' }}>DNC only</option>
                    <option value="0" {{ request('dnc') === '0' ? 'selected' : '' }}>Non-DNC</option>
                </select>
            </div>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Apply Filters</button>
        <a href="{{ route('leads.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
</form>

@if($isAgent && isset($holdingCount))
<div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
    <p class="text-sm text-slate-700">
        Holding statuses count:
        <span class="font-semibold">{{ $holdingCount }}</span> / {{ $historyLimit }}
    </p>
    <p class="mt-1 text-xs text-slate-500">
        These are your leads in holding statuses: {{ $statuses->isNotEmpty() ? $statuses->pluck('name')->join(', ') : '—' }}.
        @if($holdingCount >= $historyLimit)
        Queue is locked. Update one lead to a non-holding status to unlock new lead assignment.
        @endif
    </p>
</div>
@endif

<div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Total Debt</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Last Update</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">DNC</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Contacts</th>
                    @if($isAdmin)
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Assigned To</th>
                    @endif
                    <th scope="col" class="relative px-4 py-3"><span class="sr-only">Options</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($leads as $lead)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3">
                        <a href="{{ route('leads.show', $lead) }}" class="font-medium text-sky-600 hover:text-sky-500">{{ $lead->fullName() }}</a>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->status->name }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        @if($lead->approx_debt) ${{ number_format($lead->approx_debt, 2) }} @else — @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                        @if($lead->is_dnc)
                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">DNC</span>
                        @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
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
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $lead->assignedTo?->displayName() ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <!-- <a href="{{ route('leads.edit', $lead) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a> -->
                        <a href="{{ route('leads.edit', $lead) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 8 : 7 }}" class="px-4 py-8 text-center text-sm text-slate-500">No leads yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads instanceof \Illuminate\Contracts\Pagination\Paginator && $leads->hasPages())
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