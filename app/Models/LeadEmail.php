<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEmail extends Model
{
    protected $fillable = ['lead_id', 'email'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
