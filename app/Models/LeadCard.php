<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCard extends Model
{
    protected $fillable = [
        'lead_id',
        'bank_name',
        'bank_tollfree',
        'card_number',
        'name_on_card',
        'card_expiry',
        'card_cvc',
        'balance',
        'available_amount',
        'last_payment',
        'due_payment',
        'apr',
        'charge_card',
        'comment',
        'fees',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'available_amount' => 'decimal:2',
            'apr' => 'decimal:2',
            'charge_card' => 'boolean',
            'fees' => 'decimal:2',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
