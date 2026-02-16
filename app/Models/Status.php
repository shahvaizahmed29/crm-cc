<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    protected $fillable = ['name', 'slug'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'status_role')->withTimestamps();
    }
}
