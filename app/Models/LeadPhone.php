<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPhone extends Model
{
    protected $fillable = ['lead_id', 'phone'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
