@extends('layouts.app')

@section('title', 'Edit Lead')

@section('content')
<x-page-header title="Edit Lead — {{ $lead->fullName() }}">
    <x-slot:actions>
        <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">View</a>
        <a href="{{ route('leads.related.create', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Add Related Lead</a>
        @if(auth()->user()->isAdmin())
            <form action="{{ route('leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('Soft delete this lead?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">Delete</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-4 mt-4">
        <!-- Left Column - Lead Information -->
         <div class="xl:col-span-3">
            <form id="lead-edit-form" action="{{ route('leads.update', $lead) }}" method="POST" @if(isset($callbackStatusId) && $callbackStatusId !== null) data-callback-status-id="{{ $callbackStatusId }}" data-callback-at-utc="{{ $callbackAtUtc ?? '' }}" @endif>
                @csrf
                @method('PUT')
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 rounded-lg bg-white p-4 shadow ring-1 ring-slate-200 ">
                <div class="space-y-3 xl:col-span-1">
                        <h2 class="mb-4 text-base font-semibold text-slate-900">Lead Information</h2>
        
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

                            <x-form-field label="Fees (from cards)" for="fees">
                                <x-input name="fees" id="fees" type="text" :value="number_format((float) $lead->cards->sum('fees'), 2)" readonly disabled class="bg-slate-100 cursor-not-allowed" />
                            </x-form-field>
        
                            <x-form-field label="Details" for="details">
                                <x-input name="details" id="details" type="textarea" :value="old('details', $lead->details)" :rows="3" />
                            </x-form-field>
        
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="is_dnc" id="is_dnc" value="1" {{ old('is_dnc', $lead->is_dnc) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                                <label for="is_dnc" class="text-sm font-medium text-slate-700">Mark as DNC (Do Not Call)</label>
                            </div>
        
                            @if(isset($callbackStatusId) && $callbackStatusId !== null)
                            <div x-data="{ selectedStatusId: '{{ old('status_id', $lead->status_id) }}', callbackStatusId: '{{ $callbackStatusId }}' }">
                            @endif
                            <x-form-field label="Status" for="status_id" :required="true">
                                <x-select name="status_id" id="status_id" :options="$statuses->pluck('name', 'id')" :selected="old('status_id', $lead->status_id)" required :change-handler="isset($callbackStatusId) ? 'selectedStatusId = $event.target.value' : null" />
                            </x-form-field>

                            @if(isset($callbackStatusId) && $callbackStatusId !== null)
                            <div x-show="selectedStatusId === callbackStatusId" x-cloak class="mt-3 space-y-3 rounded-lg border border-sky-200 bg-sky-50/50 p-3">
                                <p class="text-sm font-medium text-slate-700">Callback date & time <span class="text-red-500">*</span></p>
                                <p class="text-xs text-slate-500">Set when you want to be reminded to call back. You’ll get a notification before this time. Date and time are in your local timezone. Required when status is Callback.</p>
                                <input type="hidden" name="callback_at_utc" id="callback_at_utc" value="">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <x-form-field label="Callback date" for="callback_date" :required="true">
                                        <input type="date" name="callback_date" id="callback_date" value="{{ old('callback_date', $callbackDate ?? '') }}" min="{{ now()->format('Y-m-d') }}"
                                            x-bind:required="selectedStatusId === callbackStatusId"
                                            class="mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    </x-form-field>
                                    <x-form-field label="Callback time" for="callback_time" :required="true">
                                        <input type="time" name="callback_time" id="callback_time" value="{{ old('callback_time', $callbackTime ?? '') }}"
                                            x-bind:required="selectedStatusId === callbackStatusId"
                                            class="mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                    </x-form-field>
                                </div>
                            </div>
                            </div>
                            @endif
        
                            @if(auth()->user()->isAdmin())
                            <x-form-field :label="$lead->is_deal_sheet ? 'Assigned To (Sub Agent)' : 'Assigned To (Agent)'" for="assigned_to">
                                <x-select name="assigned_to" id="assigned_to" :options="['' => 'Unassigned'] + $agents->all()" :selected="old('assigned_to', $lead->assigned_to)" />
                            </x-form-field>
                            @endif
        
                            <div>
                                <button type="submit" class="w-full rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Update Lead</button>
                            </div>
                        </div>
                </div>
        
                <!-- Middle Column - Contact Information -->
                <div class="space-y-3 xl:col-span-1">
                    @php
                    $rawPhones = old('phones');
                    if (is_array($rawPhones)) {
                        $phoneRows = array_values($rawPhones);
                    } else {
                        $phoneRows = $lead->phones->pluck('phone')->values()->all();
                    }
                    while (count($phoneRows) < 5) {
                        $phoneRows[] = '';
                    }
                    $phoneRows = array_slice($phoneRows, 0, 5);
                    $emailRows = old('emails', $lead->emails->pluck('email')->values()->all());
                    if (empty($emailRows)) {
                    $emailRows = [''];
                    }
                    @endphp
        
                    <div>
                        <h2 class="mb-4 text-base font-semibold text-slate-900">Contact Information</h2>
        
                        <div class="mb-6 space-y-2">
                            <h3 class="text-sm font-medium text-slate-700">Phone numbers</h3>
                            <p class="text-xs text-slate-500">Up to five numbers.</p>
                            @for($i = 0; $i < 5; $i++)
                            <div>
                                <label for="edit_phone_{{ $i }}" class="mb-0.5 block text-xs text-slate-600">Phone {{ $i + 1 }}</label>
                                <input type="text" name="phones[{{ $i }}]" id="edit_phone_{{ $i }}" value="{{ $phoneRows[$i] }}" placeholder="Phone number" class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            </div>
                            @endfor
                        </div>
        
                        <div x-data='@json(["emails" => array_values($emailRows)])'>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-medium text-slate-700">Email Addresses</h3>
                                <button type="button" @click="emails.push('')" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Add Email</button>
                            </div>
                            <p class="mb-2 text-xs text-slate-500">Use one field per email.</p>
                            <div class="space-y-2">
                                <template x-for="(email, index) in emails" :key="'email-' + index">
                                    <div class="flex items-center gap-2">
                                        <input type="email" :name="'emails[' + index + ']'" x-model="emails[index]" placeholder="Email address" class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                        <button type="button" x-show="emails.length > 1" @click="emails.splice(index, 1)" class="rounded-md bg-red-50 px-2 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Remove</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
         </div>
         <!-- Right Column - Sticky Cards -->
         <div class="xl:col-span-1">
             <div class="space-y-4 xl:sticky xl:top-4">
                 <!-- Credit Report Card -->
                 @php
                 $latestCr = $lead->creditReports->first();
                 $isPendingLikeCr = $latestCr && in_array($latestCr->status, ['pending', 'recheck'], true);
                 $canRequestCr = ! $isPendingLikeCr && (! $latestCr || $latestCr->status !== 'sent');
                 $canRecheckCr = $latestCr && $latestCr->status === 'notfound';
                 @endphp
 
                 <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200">
                     <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                         <h2 class="text-sm font-semibold text-slate-900">Credit Report</h2>
                         <div class="flex flex-wrap items-center gap-2">
                             <form action="{{ route('leads.credit-report.request', $lead) }}" method="POST">
                                 @csrf
                                 <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $canRequestCr ? 'bg-amber-600 hover:bg-amber-500' : 'cursor-not-allowed bg-amber-300' }}" {{ $canRequestCr ? '' : 'disabled' }}>Request CR</button>
                             </form>
                             <form action="{{ route('leads.credit-report.recheck', $lead) }}" method="POST">
                                 @csrf
                                 <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $canRecheckCr ? 'bg-indigo-600 hover:bg-indigo-500' : 'cursor-not-allowed bg-indigo-300' }}" {{ $canRecheckCr ? '' : 'disabled' }}>Re-Check CR</button>
                             </form>
                             @if($latestCr && $latestCr->report_file_path)
                             <a href="{{ route('credit-reports.download', $latestCr) }}" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Get Report</a>
                             @endif
                         </div>
                     </div>
 
                     <div class="text-sm text-slate-700">
                         <p>Status: <span class="font-semibold">{{ $latestCr ? ucfirst($latestCr->status) : 'No request yet' }}</span></p>
                         @if($latestCr && $latestCr->comment)
                         <p class="mt-1 text-xs text-slate-600">Comment: {{ $latestCr->comment }}</p>
                         @endif
                         @if($isPendingLikeCr)
                         <p class="mt-1 text-xs text-slate-600">Request is in progress. Request button stays disabled until fulfilled.</p>
                         @endif
                     </div>
 
                     @if(auth()->user()->isAdmin() && $latestCr)
                     <form action="{{ route('credit-reports.result', $latestCr) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2">
                         @csrf
                         <select name="status" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">
                             <option value="sent">Sent</option>
                             <option value="notfound">Not Found</option>
                         </select>
                         <input type="file" name="cr_file" accept=".pdf" class="w-full rounded-md border border-slate-300 bg-slate-100 cursor-pointer px-2.5 py-1.5 text-sm">
                         <input type="text" name="comment" placeholder="Comment" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">
                         <button type="submit" class="w-full rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500">Update CR</button>
                     </form>
                     @endif
                 </div>
 
                 <!-- Cards Section -->
                 <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200" x-data="{ openAddCardModal: false }">
                     <div class="flex items-center justify-between mb-3">
                         <h2 class="text-sm font-semibold text-slate-900">Cards</h2>
                         <button type="button" @click="openAddCardModal = true" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Add Card</button>
                     </div>
 
                     <div class="space-y-3 max-h-96 overflow-y-auto">
                         @forelse($lead->cards as $card)
                         <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                             <div class="flex items-start justify-between gap-3">
                                 <div>
                                     <p class="text-sm font-medium text-slate-900">{{ $card->bank_name ?: 'Unnamed Bank' }}</p>
                                     <p class="mt-0.5 text-xs text-slate-500">Card: {{ $card->card_number ?: '—' }}</p>
                                 </div>
                                 <!-- <a href="{{ route('leads.cards.edit', [$lead, $card]) }}" class="rounded bg-sky-600 px-2 py-1 text-xs font-medium text-white hover:bg-sky-500">Edit</a> -->
                                 <a href="{{ route('leads.cards.edit', [$lead, $card]) }}" class="w-6 h-6 flex items-center justify-center text-blue-500 hover:text-blue-600 text-xs rounded-full transition-all cursor-pointer">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                             </div>
                             <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-700">
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
                                 <p><span class="font-medium">Fees:</span> {{ $card->fees !== null ? '$' . number_format($card->fees, 2) : '—' }}</p>
                             </div>
                             @if($card->comment)
                             <p class="mt-2 whitespace-pre-wrap text-xs text-slate-700"><span class="font-medium">Comment:</span> {{ $card->comment }}</p>
                             @endif
                             <p class="mt-2 text-[11px] text-slate-500">
                                 Added by {{ $card->createdBy?->displayName() ?? 'Deleted User' }} on {{ format_in_app_tz($card->created_at, 'Y-m-d H:i') }}
                             </p>
                         </div>
                         @empty
                         <p class="text-sm text-slate-500">No cards yet.</p>
                         @endforelse
                     </div>
 
                     <!-- Add Card Modal (continues as before with all fields) -->
                     <div x-cloak x-show="openAddCardModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @keydown.escape.window="openAddCardModal = false">
                         <div class="w-full max-w-3xl rounded-lg bg-white p-4 shadow-xl max-h-[90vh] overflow-y-auto" @click.outside="openAddCardModal = false">
                             <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                 <h3 class="text-sm font-semibold text-slate-800">Add Card</h3>
                                 <button type="button" @click="openAddCardModal = false" class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">Close</button>
                             </div>
 
                            <form action="{{ route('leads.cards.store', $lead) }}" method="POST" class="js-card-form mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                                 <x-form-field label="Fees $" for="modal_fees">
                                     <x-input id="modal_fees" name="fees" type="number" :value="old('fees')" step="0.01" min="0" />
                                 </x-form-field>
                                 <div class="flex items-center gap-2">
                                     <input type="checkbox" name="charge_card" id="modal_charge_card" value="1" {{ old('charge_card') ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
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
 
                 <!-- Notes Section -->
                 <div class="rounded-lg bg-white p-4 shadow ring-1 ring-slate-200">
                     <h2 class="text-sm font-semibold text-slate-900 mb-3">Notes</h2>
 
                     <form action="{{ route('leads.notes.store', $lead) }}" method="POST">
                         @csrf
                         <label for="note" class="sr-only">Add note</label>
                         <textarea id="note" name="note" rows="2" class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500" placeholder="Add a note for this lead..." required>{{ old('note') }}</textarea>
                         @error('note')
                         <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                         @enderror
                         <button type="submit" class="mt-2 w-full rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500">Add Note</button>
                     </form>
 
                     <div class="mt-4 space-y-3 border-t border-slate-200 pt-4 max-h-96 overflow-y-auto">
                         @forelse($lead->notes as $note)
                         <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                             <p class="whitespace-pre-wrap text-sm text-slate-900">{{ $note->note }}</p>
                             <p class="mt-2 text-xs text-slate-500">
                                 By {{ $note->createdBy?->displayName() ?? 'Deleted User' }} on {{ format_in_app_tz($note->created_at, 'Y-m-d H:i') }}
                             </p>
                         </div>
                         @empty
                         <p class="text-sm text-slate-500">No notes yet.</p>
                         @endforelse
                     </div>
                 </div>
             </div>
         </div>        

</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || typeof jQuery.fn.validateCreditCard !== 'function') {
        return;
    }

    jQuery('.js-card-form').each(function () {
        const $form = jQuery(this);
        const $number = $form.find('input[name="card_number"]');
        const $expiry = $form.find('input[name="card_expiry"]');
        const $cvc = $form.find('input[name="card_cvc"]');
        const $submit = $form.find('button[type="submit"]').first();

        if (!$number.length) {
            return;
        }

        const ensureFeedback = function ($input) {
            let $feedback = $input.siblings('.js-field-feedback');
            if (!$feedback.length) {
                $feedback = jQuery('<p class="js-field-feedback mt-1 text-xs"></p>');
                $input.after($feedback);
            }
            return $feedback;
        };

        const $numberFeedback = ensureFeedback($number);
        const $expiryFeedback = ensureFeedback($expiry);
        const $cvcFeedback = ensureFeedback($cvc);

        const isExpiryValid = function (value) {
            if (!value) return true;
            const match = value.match(/^(0[1-9]|1[0-2])\s*\/\s*(\d{2}|\d{4})$/);
            if (!match) return false;
            const month = parseInt(match[1], 10);
            let year = parseInt(match[2], 10);
            if (match[2].length === 2) year += 2000;
            const now = new Date();
            const expiry = new Date(year, month, 0, 23, 59, 59);
            return expiry >= now;
        };

        const isCvcValid = function (value, cardTypeName) {
            if (!value) return true;
            if (!/^\d{3,4}$/.test(value)) return false;
            if (cardTypeName === 'amex') return value.length === 4;
            return value.length === 3;
        };

        const updateState = function (cardResult) {
            const rawNumber = ($number.val() || '').toString().replace(/\s|-/g, '');
            const rawExpiry = ($expiry.val() || '').toString().trim();
            const rawCvc = ($cvc.val() || '').toString().trim();
            const hasNumber = rawNumber.length > 0;
            const numberOk = !hasNumber || !!(cardResult && cardResult.valid);
            const cardName = cardResult && cardResult.card_type ? cardResult.card_type.name : null;
            const expiryOk = isExpiryValid(rawExpiry);
            const cvcOk = isCvcValid(rawCvc, cardName);
            const formOk = numberOk && expiryOk && cvcOk;

            if (hasNumber) {
                if (numberOk) {
                    $numberFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-emerald-600').text('Card number looks valid.');
                } else {
                    $numberFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-red-600').text('Invalid card number.');
                }
            } else {
                $numberFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-slate-500').text('Enter card number to validate.');
            }

            if (!rawExpiry) {
                $expiryFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-slate-500').text('Format: MM/YY');
            } else if (expiryOk) {
                $expiryFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-emerald-600').text('Expiry looks valid.');
            } else {
                $expiryFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-red-600').text('Invalid or expired date. Use MM/YY.');
            }

            if (!rawCvc) {
                $cvcFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-slate-500').text('Use 3 digits (4 for Amex).');
            } else if (cvcOk) {
                $cvcFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-emerald-600').text('CVC looks valid.');
            } else {
                $cvcFeedback.attr('class', 'js-field-feedback mt-1 text-xs text-red-600').text('Invalid CVC.');
            }

            $submit.prop('disabled', !formOk).toggleClass('opacity-60 cursor-not-allowed', !formOk);
        };

        $number.validateCreditCard(function (result) {
            updateState(result);
        });

        $expiry.on('input blur', function () { updateState($number.validateCreditCard()); });
        $cvc.on('input blur', function () { updateState($number.validateCreditCard()); });
        updateState($number.validateCreditCard());
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('lead-edit-form');
    if (!form) return;
    var callbackAtUtc = form.getAttribute('data-callback-at-utc');
    var callbackDateEl = document.getElementById('callback_date');
    var callbackTimeEl = document.getElementById('callback_time');
    if (callbackAtUtc && callbackAtUtc.trim() !== '' && callbackDateEl && callbackTimeEl) {
        var d = new Date(callbackAtUtc.trim());
        if (!isNaN(d.getTime())) {
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var h = String(d.getHours()).padStart(2, '0');
            var min = String(d.getMinutes()).padStart(2, '0');
            callbackDateEl.value = y + '-' + m + '-' + day;
            callbackTimeEl.value = h + ':' + min;
        }
    }
    form.addEventListener('submit', function () {
        var callbackStatusId = form.getAttribute('data-callback-status-id');
        var statusIdEl = document.getElementById('status_id');
        if (!callbackStatusId || !statusIdEl || statusIdEl.value !== callbackStatusId) return;
        if (!callbackDateEl || !callbackTimeEl || !callbackDateEl.value || !callbackTimeEl.value) return;
        var parts = callbackDateEl.value.split('-').map(Number);
        var timeParts = callbackTimeEl.value.split(':').map(Number);
        var localDate = new Date(parts[0], parts[1] - 1, parts[2], timeParts[0], timeParts[1] || 0, 0, 0);
        var utcInput = document.getElementById('callback_at_utc');
        if (utcInput) utcInput.value = localDate.toISOString();
    });
});
</script>
@endpush
@endsection
