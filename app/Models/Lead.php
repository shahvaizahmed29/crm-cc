<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'status_id',
        'assigned_to',
        'parent_lead_id',
        'first_name',
        'last_name',
        'address',
        'date_of_birth',
        'mothers_maiden_name',
        'ssn',
        'approx_debt',
        'fees',
        'details',
        'is_dnc',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'approx_debt' => 'decimal:2',
            'fees' => 'decimal:2',
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

    public function parentLead(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lead_id');
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

    public function relatedLeads(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lead_id')->latest();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Scope: only leads with status "new" (used for New Leads page and all new-leads counts). */
    public function scopeNewStatusOnly(Builder $query): Builder
    {
        $newStatusId = Status::where('slug', 'new')->value('id');

        return $newStatusId !== null
            ? $query->where('status_id', $newStatusId)
            : $query->whereRaw('1=0');
    }
}
