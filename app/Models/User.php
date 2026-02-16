<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'username',
        'email',
        'password',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function sessionTimes(): HasMany
    {
        return $this->hasMany(SessionTime::class);
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class, 'created_by');
    }

    public function createdLeadCards(): HasMany
    {
        return $this->hasMany(LeadCard::class, 'created_by');
    }

    public function updatedLeadCards(): HasMany
    {
        return $this->hasMany(LeadCard::class, 'updated_by');
    }

    public function leadImportHistories(): HasMany
    {
        return $this->hasMany(LeadImportHistory::class, 'uploaded_by');
    }

    public function requestedCreditReports(): HasMany
    {
        return $this->hasMany(CreditReport::class, 'requested_by');
    }

    public function processedCreditReports(): HasMany
    {
        return $this->hasMany(CreditReport::class, 'processed_by');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    public function displayName(): string
    {
        return $this->username ?: trim("{$this->name} {$this->last_name}") ?: $this->email;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
