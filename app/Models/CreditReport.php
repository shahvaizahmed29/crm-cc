<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECHECK = 'recheck';
    public const STATUS_NOT_FOUND = 'notfound';
    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'lead_id',
        'phone_number',
        'status',
        'comment',
        'report_file_path',
        'requested_by',
        'processed_by',
        'requested_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
