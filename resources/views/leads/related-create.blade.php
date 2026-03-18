@extends('layouts.app')

@section('title', 'Add Related Lead')

@section('content')
<x-page-header title="Add Related Lead — {{ $lead->fullName() }}" :back-url="route('leads.edit', $lead)" back-text="Back to Parent Lead" />
<p class="mt-1 text-sm text-slate-600">Create a related lead (for example spouse/family member). This lead is linked to the parent and excluded from holding limit counting.</p>

<form action="{{ route('leads.related.store', $lead) }}" method="POST" class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf
    <div class="space-y-3">
        <x-form-field label="First Name" for="first_name" :required="true">
            <x-input name="first_name" id="first_name" type="text" :value="old('first_name')" required />
        </x-form-field>
        <x-form-field label="Last Name" for="last_name" :required="true">
            <x-input name="last_name" id="last_name" type="text" :value="old('last_name')" required />
        </x-form-field>
        <x-form-field label="Address" for="address">
            <x-input name="address" id="address" type="text" :value="old('address', $lead->address)" />
        </x-form-field>
        <x-form-field label="Date Of Birth" for="date_of_birth">
            <x-input name="date_of_birth" id="date_of_birth" type="date" :value="old('date_of_birth')" />
        </x-form-field>
        <x-form-field label="Mother's Maiden Name" for="mothers_maiden_name">
            <x-input name="mothers_maiden_name" id="mothers_maiden_name" type="text" :value="old('mothers_maiden_name')" />
        </x-form-field>
        <x-form-field label="Social Security Number" for="ssn">
            <x-input name="ssn" id="ssn" type="text" :value="old('ssn')" />
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
            <input type="checkbox" name="is_dnc" id="is_dnc" value="1" {{ old('is_dnc') ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
            <label for="is_dnc" class="text-sm font-medium text-slate-700">Mark as DNC (Do Not Call)</label>
        </div>
        <x-form-field label="Status" for="status_id" :required="true">
            <x-select name="status_id" id="status_id" :options="$statuses->pluck('name', 'id')" :selected="old('status_id', $statuses->first()?->id)" required />
        </x-form-field>
        <div>
            <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Add Related Lead</button>
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
            <p class="mt-1 text-sm text-slate-500">Up to five numbers.</p>
            @for($i = 0; $i < 5; $i++)
            <x-form-field :label="'Phone '.($i + 1)" :for="'rel_phone_'.$i">
                <x-input name="phones[{{ $i }}]" :id="'rel_phone_'.$i" type="text" :value="$phoneRows[$i]" placeholder="Phone number" />
            </x-form-field>
            @endfor
        </div>
        <div x-data='@json(["emails" => array_values($emailRows)])'>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-900">Email Addresses</h2>
                <button type="button" @click="emails.push('')" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">Add Email</button>
            </div>
            <p class="mt-1 text-sm text-slate-500">Use one field per email.</p>
            <template x-for="(email, index) in emails" :key="'email-' + index">
                <div class="mt-2 flex items-center gap-2">
                    <input type="email" :name="'emails[' + index + ']'" x-model="emails[index]" placeholder="Email address" class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    <button type="button" x-show="emails.length > 1" @click="emails.splice(index, 1)" class="rounded-md bg-red-50 px-2 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Remove</button>
                </div>
            </template>
        </div>
    </div>
</form>
@endsection

