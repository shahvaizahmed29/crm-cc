<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(Status::class, 'status_role')->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function isAgent(): bool
    {
        return $this->slug === 'agent';
    }
}
