<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Parses deal-sheet .txt files using tolerant, format-agnostic heuristics.
 * Supports legacy export format and common third-party variants.
 */
final class LeadTxtExportParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseFile(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $lines = preg_split('/\n/', $normalized) ?: [];

        $blocks = $this->splitIntoLeadBlocks($lines);
        $out = [];
        foreach ($blocks as $block) {
            $parsed = $this->parseLeadBlock(implode("\n", $block));
            if (trim((string) ($parsed['first_name'] ?? '')) === '' && trim((string) ($parsed['last_name'] ?? '')) === '') {
                continue;
            }
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function parseLeadBlock(string $text): array
    {
        $lines = preg_split('/\n/', str_replace("\r", '', $text)) ?: [];
        $lines = array_map(static fn (string $l): string => trim($l), $lines);

        $rawName = '';
        $phones = [];
        $address = null;
        $city = null;
        $state = null;
        $zip = null;
        $dobRaw = null;
        $mmn = null;
        $ssn = null;
        $email = null;
        $detailsLines = [];
        $approxDebt = null;
        $fees = null;
        $totalCards = null;

        $cards = [];
        $currentCard = null;

        foreach ($lines as $line) {
            if ($line === '' || $this->isSeparatorLine($line)) {
                continue;
            }

            $pair = $this->parseKeyValueLine($line);
            if ($pair === null) {
                if ($this->looksLikeCardTitle($line)) {
                    if ($currentCard !== null) {
                        $cards[] = $currentCard;
                    }
                    $currentCard = $this->newCardTemplate();
                    $currentCard['bank_name'] = $line;
                    $currentCard['charge_card'] = stripos($line, 'charge card') !== false;
                    continue;
                }

                $detailsLines[] = $line;
                continue;
            }

            [$rawKey, $rawValue] = $pair;
            $key = $this->normalizeKey($rawKey);
            $value = trim($rawValue);

            if ($key === 'bank') {
                if ($currentCard !== null) {
                    $cards[] = $currentCard;
                }
                $currentCard = $this->newCardTemplate();
                $currentCard['bank_name'] = $value;
                $currentCard['charge_card'] = stripos($value, 'charge card') !== false;
                continue;
            }

            if ($this->isCardKey($key)) {
                if ($currentCard === null) {
                    $currentCard = $this->newCardTemplate();
                }
                $this->applyCardValue($currentCard, $key, $value);
                continue;
            }

            switch ($key) {
                case 'name':
                case 'customer_name':
                    $rawName = $value;
                    break;
                case 'phone':
                case 'phone_no':
                case 'alt_phone':
                case 'phone_2':
                case 'phone_3':
                case 'phone_4':
                case 'phone_5':
                    if ($value !== '') {
                        $phones[] = $value;
                    }
                    break;
                case 'address':
                    $address = $value !== '' ? $value : $address;
                    break;
                case 'city':
                    $city = $value !== '' ? $value : $city;
                    break;
                case 'state':
                    $state = $value !== '' ? $value : $state;
                    break;
                case 'zip':
                case 'zip_code':
                case 'zipcode':
                    $zip = $value !== '' ? $value : $zip;
                    break;
                case 'dob':
                case 'date_of_birth':
                    $dobRaw = $value !== '' ? $value : $dobRaw;
                    break;
                case 'mmn':
                case 'mothers_maiden_name':
                    $mmn = $value !== '' ? $value : $mmn;
                    break;
                case 'ssn':
                    $ssn = $value !== '' ? $value : $ssn;
                    break;
                case 'email':
                case 'e_mail':
                    if ($value !== '' && strtolower($value) !== 'none') {
                        $email = $value;
                    }
                    break;
                case 'total_debt':
                case 'tdebt':
                    $approxDebt = $this->parseMoney($value) ?? $approxDebt;
                    break;
                case 'charge':
                case 'fee':
                case 'fees':
                case 'charge_amount':
                    $fees = $this->parseMoney($value) ?? $fees;
                    break;
                case 'total_cards':
                case 'tcards':
                    $totalCards = $this->parseInteger($value);
                    break;
                default:
                    if ($value !== '') {
                        $detailsLines[] = "{$rawKey}: {$value}";
                    }
                    break;
            }
        }

        if ($currentCard !== null) {
            $cards[] = $currentCard;
        }

        $cards = array_values(array_filter($cards, function (array $c): bool {
            return trim((string) ($c['bank_name'] ?? '')) !== ''
                || trim((string) ($c['card_number'] ?? '')) !== ''
                || $c['balance'] !== null;
        }));

        [$firstName, $lastName] = $this->splitFullName($rawName);
        $addressCombined = $this->combineAddress($address, $city, $state, $zip);
        $dob = $this->parseDob((string) ($dobRaw ?? ''));
        $phones = array_values(array_unique(array_filter(array_map('trim', $phones), fn ($p) => $p !== '')));
        $phones = array_slice($phones, 0, 5);

        if ($approxDebt === null && $cards !== []) {
            $sum = 0.0;
            foreach ($cards as $card) {
                $sum += (float) ($card['balance'] ?? 0);
            }
            $approxDebt = $sum > 0 ? round($sum, 2) : null;
        }

        if ($fees === null && $cards !== []) {
            $sum = 0.0;
            foreach ($cards as $card) {
                $sum += (float) ($card['fees'] ?? 0);
            }
            $fees = $sum > 0 ? round($sum, 2) : null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phones' => $phones,
            'address' => $addressCombined,
            'date_of_birth' => $dob,
            'mothers_maiden_name' => $this->sanitizeNullable($mmn),
            'ssn' => $this->sanitizeNullable($ssn),
            'email' => $this->sanitizeNullable($email),
            'details' => $this->sanitizeNullable(implode("\n", array_slice($detailsLines, 0, 200))),
            'approx_debt' => $approxDebt,
            'fees' => $fees,
            'cards' => $cards,
            'meta' => [
                'tcards' => $totalCards,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array<int, string>>
     */
    private function splitIntoLeadBlocks(array $lines): array
    {
        $blocks = [];
        $current = [];
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = rtrim((string) $lines[$i]);
            $trim = trim($line);
            if ($trim === '') {
                $current[] = $line;
                continue;
            }

            if ($this->isSeparatorLine($trim)) {
                $looksLikeNextIsNewLead = false;
                for ($j = $i + 1; $j < min($lineCount, $i + 8); $j++) {
                    $peek = trim((string) $lines[$j]);
                    if ($peek === '' || $this->isSeparatorLine($peek)) {
                        continue;
                    }
                    if ($this->looksLikeLeadStart($peek)) {
                        $looksLikeNextIsNewLead = true;
                    }
                    break;
                }

                if ($looksLikeNextIsNewLead && $this->blockHasLeadSignal($current)) {
                    $blocks[] = $current;
                    $current = [];
                    continue;
                }
            }

            $current[] = $line;
        }

        if ($this->blockHasLeadSignal($current)) {
            $blocks[] = $current;
        }

        if ($blocks === [] && $lines !== []) {
            $blocks[] = $lines;
        }

        return $blocks;
    }

    /** @param array<int, string> $block */
    private function blockHasLeadSignal(array $block): bool
    {
        foreach ($block as $line) {
            if ($this->looksLikeLeadStart(trim($line))) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeLeadStart(string $line): bool
    {
        return (bool) preg_match('/^(customer\s*name|name|id)\s*:/i', $line);
    }

    private function isSeparatorLine(string $line): bool
    {
        return (bool) preg_match('/^[=\-]{8,}$/', trim($line));
    }

    /** @return array{0:string,1:string}|null */
    private function parseKeyValueLine(string $line): ?array
    {
        if (preg_match('/^\s*([^:]{1,50}?)\s*:\s*(.*)$/', $line, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return null;
    }

    private function normalizeKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = str_replace(['.', '/', '\\', '#'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $aliases = [
            'customer name' => 'customer_name',
            'name' => 'name',
            'phone' => 'phone',
            'phone no' => 'phone_no',
            'alt phone' => 'alt_phone',
            'phone 2' => 'phone_2',
            'phone 3' => 'phone_3',
            'phone 4' => 'phone_4',
            'phone 5' => 'phone_5',
            'address' => 'address',
            'add' => 'address',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip',
            'zip code' => 'zip_code',
            'zipcode' => 'zipcode',
            'ssn' => 'ssn',
            's s n' => 'ssn',
            'dob' => 'dob',
            'd o b' => 'dob',
            'date of birth' => 'date_of_birth',
            'mmn' => 'mmn',
            'm m n' => 'mmn',
            'mothers maiden name' => 'mothers_maiden_name',
            'e mail' => 'e_mail',
            'email' => 'email',
            'total debt' => 'total_debt',
            'tdebt' => 'tdebt',
            'total cards' => 'total_cards',
            'total card' => 'total_cards',
            'tcards' => 'tcards',
            'charge' => 'charge',
            'charge amount' => 'charge_amount',
            'fees' => 'fees',
            'fee' => 'fee',
            'saving' => 'saving',
            'savings' => 'savings',
            'bank' => 'bank',
            'bn' => 'bank',
            'card holder name' => 'card_holder_name',
            'bt' => 'bank_tollfree',
            'toll free' => 'bank_tollfree',
            'tollfree' => 'bank_tollfree',
            'tollfree#' => 'bank_tollfree',
            'cc' => 'card_number',
            'cc#' => 'card_number',
            'card no' => 'card_number',
            'card number' => 'card_number',
            'cvc' => 'card_cvc',
            'exp' => 'card_expiry',
            'bal' => 'balance',
            'avl' => 'available_amount',
            'av' => 'available_amount',
            'lp' => 'last_payment',
            'dp' => 'due_payment',
            'apr' => 'apr',
            'rate' => 'apr',
            'comment' => 'comment',
            'v method' => 'verification_method',
            'v m' => 'verification_method',
        ];

        return $aliases[$normalized] ?? str_replace(' ', '_', $normalized);
    }

    private function isCardKey(string $key): bool
    {
        return in_array($key, [
            'card_holder_name',
            'bank_tollfree',
            'card_number',
            'card_cvc',
            'card_expiry',
            'balance',
            'available_amount',
            'last_payment',
            'due_payment',
            'apr',
            'comment',
            'verification_method',
        ], true);
    }

    /** @return array<string,mixed> */
    private function newCardTemplate(): array
    {
        return [
            'bank_name' => '',
            'charge_card' => false,
            'name_on_card' => '',
            'bank_tollfree' => '',
            'card_number' => '',
            'card_expiry' => '',
            'card_cvc' => '',
            'balance' => null,
            'available_amount' => null,
            'last_payment' => '',
            'due_payment' => '',
            'apr' => null,
            'comment' => '',
            'fees' => null,
        ];
    }

    /** @param array<string,mixed> $card */
    private function applyCardValue(array &$card, string $key, string $value): void
    {
        switch ($key) {
            case 'card_holder_name':
                $card['name_on_card'] = $value;
                break;
            case 'bank_tollfree':
                $card['bank_tollfree'] = $value;
                break;
            case 'card_number':
                $card['card_number'] = $value;
                break;
            case 'card_cvc':
                $card['card_cvc'] = $value;
                break;
            case 'card_expiry':
                $card['card_expiry'] = $value;
                break;
            case 'balance':
                $card['balance'] = $this->parseMoney($value);
                break;
            case 'available_amount':
                $card['available_amount'] = $this->parseMoney($value);
                break;
            case 'last_payment':
                $card['last_payment'] = $value;
                break;
            case 'due_payment':
                $card['due_payment'] = $value;
                break;
            case 'apr':
                $card['apr'] = $this->parseMoney($value);
                break;
            case 'comment':
            case 'verification_method':
                $card['comment'] = trim(($card['comment'] ?? '') . ($card['comment'] !== '' ? ' | ' : '') . $value);
                break;
        }
    }

    private function looksLikeCardTitle(string $line): bool
    {
        if ($line === '' || preg_match('/^\d+$/', $line)) {
            return false;
        }
        if ($this->parseKeyValueLine($line) !== null) {
            return false;
        }

        return (bool) preg_match('/\b(visa|master|amex|discover|bank|capital|cap1|citi|credit|wells|merrick)\b/i', $line);
    }

    /** @return array{0:string,1:string} */
    private function splitFullName(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return ['Unknown', 'Lead'];
        }
        $parts = preg_split('/\s+/', $full) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        return [$parts[0], implode(' ', array_slice($parts, 1))];
    }

    private function combineAddress(?string $address, ?string $city, ?string $state, ?string $zip): ?string
    {
        $chunks = [];
        if ($address !== null && trim($address) !== '') {
            $chunks[] = trim($address);
        }

        $csz = trim(implode(' ', array_filter([
            $city !== null ? trim($city) : '',
            $state !== null ? trim($state) : '',
            $zip !== null ? trim($zip) : '',
        ], fn ($v) => $v !== '')));

        if ($csz !== '') {
            $chunks[] = $csz;
        }

        return $chunks === [] ? null : implode(', ', $chunks);
    }

    private function parseDob(string $raw): ?CarbonInterface
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\*+([\/\-]\*+)*$/', str_replace(' ', '', $raw))) {
            return null;
        }

        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm-d-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $raw)->startOfDay();
            } catch (\Throwable) {
                // try next
            }
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMoney(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw)) ?? '';
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        return round((float) $clean, 2);
    }

    private function parseInteger(string $raw): ?int
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        return (int) $digits;
    }

    private function sanitizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'none') {
            return null;
        }

        return $value;
    }
}
