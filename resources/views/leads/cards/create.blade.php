@extends('layouts.app')

@section('title', 'Add Card')

@section('content')
<x-page-header title="Add Card — {{ $lead->fullName() }}">
    <x-slot:actions>
        <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Back to Lead</a>
    </x-slot:actions>
</x-page-header>

<form action="{{ route('leads.cards.store', $lead) }}" method="POST" class="js-card-form mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    @csrf
    <x-form-field label="Bank Name" for="bank_name">
        <x-input id="bank_name" name="bank_name" type="text" :value="old('bank_name')" />
    </x-form-field>
    <x-form-field label="Bank Tollfree" for="bank_tollfree">
        <x-input id="bank_tollfree" name="bank_tollfree" type="text" :value="old('bank_tollfree')" />
    </x-form-field>
    <x-form-field label="Credit Card Number" for="card_number">
        <x-input id="card_number" name="card_number" type="text" :value="old('card_number')" />
    </x-form-field>
    <x-form-field label="Name On Card" for="name_on_card">
        <x-input id="name_on_card" name="name_on_card" type="text" :value="old('name_on_card')" />
    </x-form-field>
    <x-form-field label="Card Expiry (MM/YY)" for="card_expiry">
        <x-input id="card_expiry" name="card_expiry" type="text" :value="old('card_expiry')" />
    </x-form-field>
    <x-form-field label="CVC" for="card_cvc">
        <x-input id="card_cvc" name="card_cvc" type="text" :value="old('card_cvc')" />
    </x-form-field>
    <x-form-field label="Balance $" for="balance">
        <x-input id="balance" name="balance" type="number" :value="old('balance')" step="0.01" min="0" />
    </x-form-field>
    <x-form-field label="Available $" for="available_amount">
        <x-input id="available_amount" name="available_amount" type="number" :value="old('available_amount')" step="0.01" min="0" />
    </x-form-field>
    <x-form-field label="Last Payment" for="last_payment">
        <x-input id="last_payment" name="last_payment" type="text" :value="old('last_payment')" />
    </x-form-field>
    <x-form-field label="Due Payment" for="due_payment">
        <x-input id="due_payment" name="due_payment" type="text" :value="old('due_payment')" />
    </x-form-field>
    <x-form-field label="APR %" for="apr">
        <x-input id="apr" name="apr" type="number" :value="old('apr')" step="0.01" min="0" />
    </x-form-field>
    <x-form-field label="Fees $" for="fees">
        <x-input id="fees" name="fees" type="number" :value="old('fees')" step="0.01" min="0" />
    </x-form-field>
    <div class="flex items-center gap-2">
        <input
            type="checkbox"
            name="charge_card"
            id="charge_card"
            value="1"
            {{ old('charge_card') ? 'checked' : '' }}
            class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
        >
        <label for="charge_card" class="text-sm font-medium text-slate-700">Charge Card</label>
    </div>
    <div class="lg:col-span-2">
        <x-form-field label="Comment" for="comment">
            <x-input id="comment" name="comment" type="textarea" :value="old('comment')" :rows="3" />
        </x-form-field>
    </div>

    <div class="lg:col-span-2">
        <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500">Save Card</button>
    </div>
</form>

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
</script>
@endpush
@endsection
