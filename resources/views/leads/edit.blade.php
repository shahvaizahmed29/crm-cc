@extends('layouts.app')

@section('title', 'Edit Lead')

@section('content')
<x-page-header title="Edit Lead — {{ $lead->fullName() }}">
    <x-slot:actions>
        <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">View</a>
    </x-slot:actions>
</x-page-header>

<form action="{{ route('leads.update', $lead) }}" method="POST" class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf
    @method('PUT')
    <div class="space-y-3">
        <x-form-field label="First Name" for="first_name" :required="true">
            <x-input name="first_name" id="first_name" type="text" :value="old('first_name', $lead->first_name)" required />
        </x-form-field>
        <x-form-field label="Last Name" for="last_name" :required="true">
            <x-input name="last_name" id="last_name" type="text" :value="old('last_name', $lead->last_name)" required />
        </x-form-field>
        <x-form-field label="Address" for="address">
            <x-input name="address" id="address" type="text" :value="old('address', $lead->address)" />
        </x-form-field>
        <x-form-field label="Date Of Birth" for="date_of_birth">
            <x-input name="date_of_birth" id="date_of_birth" type="date" :value="old('date_of_birth', $lead->date_of_birth?->format('Y-m-d'))" />
        </x-form-field>
        <x-form-field label="Mother's Maiden Name" for="mothers_maiden_name">
            <x-input name="mothers_maiden_name" id="mothers_maiden_name" type="text" :value="old('mothers_maiden_name', $lead->mothers_maiden_name)" />
        </x-form-field>
        <x-form-field label="Social Security Number" for="ssn">
            <x-input name="ssn" id="ssn" type="text" :value="old('ssn', $lead->ssn)" />
        </x-form-field>
        <x-form-field label="Approx Debt" for="approx_debt">
            <x-input name="approx_debt" id="approx_debt" type="number" :value="old('approx_debt', $lead->approx_debt)" />
        </x-form-field>
        <x-form-field label="Details" for="details">
            <x-input name="details" id="details" type="textarea" :value="old('details', $lead->details)" :rows="3" />
        </x-form-field>
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                name="is_dnc"
                id="is_dnc"
                value="1"
                {{ old('is_dnc', $lead->is_dnc) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500"
            >
            <label for="is_dnc" class="text-sm font-medium text-slate-700">Mark as DNC (Do Not Call)</label>
        </div>
        <x-form-field label="Status" for="status_id" :required="true">
            <x-select name="status_id" id="status_id" :options="$statuses->pluck('name', 'id')" :selected="old('status_id', $lead->status_id)" required />
        </x-form-field>
        @if(auth()->user()->isAdmin())
        <x-form-field label="Assigned To" for="assigned_to">
            <x-select name="assigned_to" id="assigned_to" :options="['' => 'Unassigned'] + $agents->all()" :selected="old('assigned_to', $lead->assigned_to)" />
        </x-form-field>
        @endif
        <div>
            <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Update Lead</button>
        </div>
    </div>

    @php
        $phoneRows = old('phones', $lead->phones->pluck('phone')->values()->all());
        $emailRows = old('emails', $lead->emails->pluck('email')->values()->all());
        if (empty($phoneRows)) {
            $phoneRows = [''];
        }
        if (empty($emailRows)) {
            $emailRows = [''];
        }
    @endphp
    <div class="space-y-4" x-data='@json(["phones" => array_values($phoneRows), "emails" => array_values($emailRows)])'>
        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-900">Phone numbers</h2>
                <button type="button" @click="phones.push('')" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                    Add Phone
                </button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Use one field per number.</p>
            <template x-for="(phone, index) in phones" :key="'phone-' + index">
                <div class="mt-2 flex items-center gap-2">
                    <input type="text" :name="'phones[' + index + ']'" x-model="phones[index]" placeholder="Phone number"
                        class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    <button type="button" x-show="phones.length > 1" @click="phones.splice(index, 1)"
                        class="rounded-md bg-red-50 px-2 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                        Remove
                    </button>
                </div>
            </template>
        </div>
        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-900">Email addresses</h2>
                <button type="button" @click="emails.push('')" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                    Add Email
                </button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Use one field per email.</p>
            <template x-for="(email, index) in emails" :key="'email-' + index">
                <div class="mt-2 flex items-center gap-2">
                    <input type="email" :name="'emails[' + index + ']'" x-model="emails[index]" placeholder="Email address"
                        class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    <button type="button" x-show="emails.length > 1" @click="emails.splice(index, 1)"
                        class="rounded-md bg-red-50 px-2 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                        Remove
                    </button>
                </div>
            </template>
        </div>
    </div>
</form>

<div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
    @php
        $latestCr = $lead->creditReports->first();
        $isPendingLikeCr = $latestCr && in_array($latestCr->status, ['pending', 'recheck'], true);
        $canRequestCr = ! $isPendingLikeCr && (! $latestCr || $latestCr->status !== 'sent');
        $canRecheckCr = $latestCr && $latestCr->status === 'notfound';
    @endphp
    <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200 lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-medium text-slate-500">Credit Report</h2>
            <div class="flex flex-wrap items-center gap-2">
                <form action="{{ route('leads.credit-report.request', $lead) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $canRequestCr ? 'bg-amber-600 hover:bg-amber-500' : 'cursor-not-allowed bg-amber-300' }}"
                        {{ $canRequestCr ? '' : 'disabled' }}
                    >
                        Request CR
                    </button>
                </form>
                <form action="{{ route('leads.credit-report.recheck', $lead) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $canRecheckCr ? 'bg-indigo-600 hover:bg-indigo-500' : 'cursor-not-allowed bg-indigo-300' }}"
                        {{ $canRecheckCr ? '' : 'disabled' }}
                    >
                        Re-Check CR
                    </button>
                </form>
                @if($latestCr && $latestCr->report_file_path)
                    <a href="{{ route('credit-reports.download', $latestCr) }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Get Report</a>
                @endif
            </div>
        </div>

        <div class="mt-3 text-sm text-slate-700">
            <p>Status: <span class="font-semibold">{{ $latestCr ? ucfirst($latestCr->status) : 'No request yet' }}</span></p>
            @if($latestCr && $latestCr->comment)
                <p class="mt-1 text-xs text-slate-600">Comment: {{ $latestCr->comment }}</p>
            @endif
            @if($isPendingLikeCr)
                <p class="mt-1 text-xs text-slate-600">Request is in progress. Request button stays disabled until fulfilled.</p>
            @endif
        </div>

        @if(auth()->user()->isAdmin() && $latestCr)
            <form action="{{ route('credit-reports.result', $latestCr) }}" method="POST" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                @csrf
                <select name="status" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">
                    <option value="sent">Sent</option>
                    <option value="notfound">Not Found</option>
                </select>
                <input type="file" name="cr_file" accept=".pdf" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">
                <input type="text" name="comment" placeholder="Comment" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">
                <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500">Update CR</button>
            </form>
        @endif
    </div>

    <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200" x-data="{ openAddCardModal: false }">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-medium text-slate-500">Cards</h2>
            <button type="button" @click="openAddCardModal = true" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Add Card</button>
        </div>
        <div class="mt-3 space-y-3">
            @forelse($lead->cards as $card)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $card->bank_name ?: 'Unnamed Bank' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">Card: {{ $card->card_number ?: '—' }}</p>
                        </div>
                        <a href="{{ route('leads.cards.edit', [$lead, $card]) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a>
                    </div>
                    <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-700 sm:grid-cols-2">
                        <p><span class="font-medium">Tollfree:</span> {{ $card->bank_tollfree ?: '—' }}</p>
                        <p><span class="font-medium">Name On Card:</span> {{ $card->name_on_card ?: '—' }}</p>
                        <p><span class="font-medium">Expiry:</span> {{ $card->card_expiry ?: '—' }}</p>
                        <p><span class="font-medium">CVC:</span> {{ $card->card_cvc ?: '—' }}</p>
                        <p><span class="font-medium">Balance:</span> {{ $card->balance !== null ? '$' . number_format($card->balance, 2) : '—' }}</p>
                        <p><span class="font-medium">Available:</span> {{ $card->available_amount !== null ? '$' . number_format($card->available_amount, 2) : '—' }}</p>
                        <p><span class="font-medium">Last Payment:</span> {{ $card->last_payment ?: '—' }}</p>
                        <p><span class="font-medium">Due Payment:</span> {{ $card->due_payment ?: '—' }}</p>
                        <p><span class="font-medium">APR:</span> {{ $card->apr !== null ? number_format($card->apr, 2) . '%' : '—' }}</p>
                        <p><span class="font-medium">Charge Card:</span> {{ $card->charge_card ? 'Yes' : 'No' }}</p>
                    </div>
                    @if($card->comment)
                        <p class="mt-2 whitespace-pre-wrap text-xs text-slate-700"><span class="font-medium">Comment:</span> {{ $card->comment }}</p>
                    @endif
                    <p class="mt-2 text-[11px] text-slate-500">
                        Added by {{ $card->createdBy?->displayName() ?? 'Deleted User' }} on {{ $card->created_at->format('Y-m-d H:i') }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No cards yet.</p>
            @endforelse
        </div>

        <div
            x-cloak
            x-show="openAddCardModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
            @keydown.escape.window="openAddCardModal = false"
        >
            <div class="w-full max-w-3xl rounded-lg bg-white p-4 shadow-xl" @click.outside="openAddCardModal = false">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <h3 class="text-sm font-semibold text-slate-800">Add Card</h3>
                    <button type="button" @click="openAddCardModal = false" class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">Close</button>
                </div>

                <form action="{{ route('leads.cards.store', $lead) }}" method="POST" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @csrf
                    <x-form-field label="Bank Name" for="modal_bank_name">
                        <x-input id="modal_bank_name" name="bank_name" type="text" :value="old('bank_name')" />
                    </x-form-field>
                    <x-form-field label="Bank Tollfree" for="modal_bank_tollfree">
                        <x-input id="modal_bank_tollfree" name="bank_tollfree" type="text" :value="old('bank_tollfree')" />
                    </x-form-field>
                    <x-form-field label="Credit Card Number" for="modal_card_number">
                        <x-input id="modal_card_number" name="card_number" type="text" :value="old('card_number')" />
                    </x-form-field>
                    <x-form-field label="Name On Card" for="modal_name_on_card">
                        <x-input id="modal_name_on_card" name="name_on_card" type="text" :value="old('name_on_card')" />
                    </x-form-field>
                    <x-form-field label="Card Expiry (MM/YY)" for="modal_card_expiry">
                        <x-input id="modal_card_expiry" name="card_expiry" type="text" :value="old('card_expiry')" />
                    </x-form-field>
                    <x-form-field label="CVC" for="modal_card_cvc">
                        <x-input id="modal_card_cvc" name="card_cvc" type="text" :value="old('card_cvc')" />
                    </x-form-field>
                    <x-form-field label="Balance $" for="modal_balance">
                        <x-input id="modal_balance" name="balance" type="number" :value="old('balance')" step="0.01" min="0" />
                    </x-form-field>
                    <x-form-field label="Available $" for="modal_available_amount">
                        <x-input id="modal_available_amount" name="available_amount" type="number" :value="old('available_amount')" step="0.01" min="0" />
                    </x-form-field>
                    <x-form-field label="Last Payment" for="modal_last_payment">
                        <x-input id="modal_last_payment" name="last_payment" type="text" :value="old('last_payment')" />
                    </x-form-field>
                    <x-form-field label="Due Payment" for="modal_due_payment">
                        <x-input id="modal_due_payment" name="due_payment" type="text" :value="old('due_payment')" />
                    </x-form-field>
                    <x-form-field label="APR %" for="modal_apr">
                        <x-input id="modal_apr" name="apr" type="number" :value="old('apr')" step="0.01" min="0" />
                    </x-form-field>
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="charge_card"
                            id="modal_charge_card"
                            value="1"
                            {{ old('charge_card') ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                        >
                        <label for="modal_charge_card" class="text-xs font-medium uppercase tracking-wide text-slate-600">Charge Card</label>
                    </div>

                    <div class="sm:col-span-2">
                        <x-form-field label="Comment" for="modal_comment">
                            <x-input id="modal_comment" name="comment" type="textarea" :value="old('comment')" :rows="3" />
                        </x-form-field>
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2 border-t border-slate-200 pt-3">
                        <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500">Save Card</button>
                        <button type="button" @click="openAddCardModal = false" class="rounded-md bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200">
        <h2 class="text-sm font-medium text-slate-500">Notes</h2>
        <form action="{{ route('leads.notes.store', $lead) }}" method="POST" class="mt-3">
            @csrf
            <label for="note" class="sr-only">Add note</label>
            <textarea
                id="note"
                name="note"
                rows="2"
                class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                placeholder="Add a note for this lead..."
                required
            >{{ old('note') }}</textarea>
            @error('note')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="mt-2 rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500">Add Note</button>
        </form>

        <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
            @forelse($lead->notes as $note)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <p class="whitespace-pre-wrap text-sm text-slate-900">{{ $note->note }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                        By {{ $note->createdBy?->displayName() ?? 'Deleted User' }} on {{ $note->created_at->format('Y-m-d H:i') }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No notes yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
