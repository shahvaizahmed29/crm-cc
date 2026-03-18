@extends('layouts.app')

@section('title', 'Add Lead')

@section('content')
<x-page-header title="Add Lead Information" :back-url="route('leads.index')" back-text="Back to list" />
<p class="mt-1 text-sm text-slate-600">Enter all possible information.</p>

<form id="lead-create-form" action="{{ route('leads.store') }}" method="POST" class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2" @if(isset($callbackStatusId) && $callbackStatusId !== null) data-callback-status-id="{{ $callbackStatusId }}" @endif>
    @csrf
    <div class="space-y-3">
        <x-form-field label="First Name" for="first_name" :required="true">
            <x-input name="first_name" id="first_name" type="text" :value="old('first_name')" required />
        </x-form-field>
        <x-form-field label="Last Name" for="last_name" :required="true">
            <x-input name="last_name" id="last_name" type="text" :value="old('last_name')" required />
        </x-form-field>
        <x-form-field label="Address" for="address">
            <x-input name="address" id="address" type="text" :value="old('address')" />
        </x-form-field>
        <x-form-field label="Date Of Birth" for="date_of_birth">
            <x-input name="date_of_birth" id="date_of_birth" type="date" :value="old('date_of_birth')" />
        </x-form-field>
        <x-form-field label="Mother's Maiden Name" for="mothers_maiden_name">
            <x-input name="mothers_maiden_name" id="mothers_maiden_name" type="text" :value="old('mothers_maiden_name')" placeholder="MMN" />
        </x-form-field>
        <x-form-field label="Social Security Number" for="ssn">
            <x-input name="ssn" id="ssn" type="text" :value="old('ssn')" placeholder="SSN" />
        </x-form-field>
        <x-form-field label="Approx Debt" for="approx_debt">
            <x-input name="approx_debt" id="approx_debt" type="number" :value="old('approx_debt')" />
        </x-form-field>
        <x-form-field label="Fees" for="fees">
            <x-input name="fees" id="fees" type="number" :value="old('fees')" />
        </x-form-field>
        <x-form-field label="Details" for="details">
            <x-input name="details" id="details" type="textarea" :value="old('details')" :rows="3" />
        </x-form-field>
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                name="is_dnc"
                id="is_dnc"
                value="1"
                {{ old('is_dnc') ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500"
            >
            <label for="is_dnc" class="text-sm font-medium text-slate-700">Mark as DNC (Do Not Call)</label>
        </div>
        @if(isset($callbackStatusId) && $callbackStatusId !== null)
        <div x-data="{ selectedStatusId: '{{ old('status_id', '') }}', callbackStatusId: '{{ $callbackStatusId }}' }">
        @endif
        <x-form-field label="Status" for="status_id" :required="true">
            <x-select name="status_id" id="status_id" :options="$statuses->pluck('name', 'id')" :selected="old('status_id')" required :change-handler="isset($callbackStatusId) ? 'selectedStatusId = $event.target.value' : null" />
        </x-form-field>
        @if(isset($callbackStatusId) && $callbackStatusId !== null)
        <div x-show="selectedStatusId === callbackStatusId" x-cloak class="mt-3 space-y-3 rounded-lg border border-sky-200 bg-sky-50/50 p-3">
            <p class="text-sm font-medium text-slate-700">Callback date & time <span class="text-red-500">*</span></p>
            <p class="text-xs text-slate-500">Set when you want to be reminded to call back. Required when status is Callback.</p>
            <input type="hidden" name="callback_at_utc" id="callback_at_utc" value="">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-form-field label="Callback date" for="callback_date" :required="true">
                    <input type="date" name="callback_date" id="callback_date" value="{{ old('callback_date', '') }}" min="{{ now()->format('Y-m-d') }}"
                        x-bind:required="selectedStatusId === callbackStatusId"
                        class="mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </x-form-field>
                <x-form-field label="Callback time" for="callback_time" :required="true">
                    <input type="time" name="callback_time" id="callback_time" value="{{ old('callback_time', '') }}"
                        x-bind:required="selectedStatusId === callbackStatusId"
                        class="mt-0.5 block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </x-form-field>
            </div>
        </div>
        </div>
        @endif
        <div>
            <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Add Lead</button>
        </div>
    </div>

    @php
        $rawPhones = old('phones');
        $phoneRows = [];
        for ($i = 0; $i < 5; $i++) {
            $phoneRows[] = is_array($rawPhones) ? (string) ($rawPhones[$i] ?? '') : '';
        }
        $emailRows = old('emails', ['']);
        if (empty($emailRows)) {
            $emailRows = [''];
        }
    @endphp
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-medium text-slate-900">Phone numbers</h2>
            <p class="mt-1 text-sm text-slate-500">Up to five numbers (leave blank if not used).</p>
            @for($i = 0; $i < 5; $i++)
            <x-form-field :label="'Phone '.($i + 1)" :for="'phone_'.$i">
                <x-input name="phones[{{ $i }}]" :id="'phone_'.$i" type="text" :value="$phoneRows[$i]" placeholder="Phone number" />
            </x-form-field>
            @endfor
        </div>
        <div x-data='@json(["emails" => array_values($emailRows)])'>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-900">Add Email Addresses</h2>
                <button type="button" @click="emails.push('')" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                    Add Email
                </button>
            </div>
            <p class="mt-1 text-sm text-slate-500">Use one field per email.</p>
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

@if(isset($callbackStatusId) && $callbackStatusId !== null)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('lead-create-form');
    if (!form) return;
    var callbackDateEl = document.getElementById('callback_date');
    var callbackTimeEl = document.getElementById('callback_time');
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
@endif
@endsection
