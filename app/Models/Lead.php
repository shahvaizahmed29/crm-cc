<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_id',
        'assigned_to',
        'first_name',
        'last_name',
        'address',
        'date_of_birth',
        'mothers_maiden_name',
        'ssn',
        'approx_debt',
        'details',
        'is_dnc',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'approx_debt' => 'decimal:2',
            'is_dnc' => 'boolean',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(LeadPhone::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function cards(): HasMany
    {
        return $this->hasMany(LeadCard::class)->latest();
    }

    public function creditReports(): HasMany
    {
        return $this->hasMany(CreditReport::class)->latest();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
