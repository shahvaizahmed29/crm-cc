<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrmNotification extends Model
{
    protected $table = 'crm_notifications';

    protected $fillable = [
        'uuid',
        'created_by',
        'target_user_id',
        'type',
        'entity_type',
        'entity_id',
        'title',
        'message',
        'action_url',
        'notify_at',
        'read_at',
        'sent_at',
        'priority',
        'status',
        'attempts',
        'last_error',
        'dedupe_key',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'notify_at' => 'datetime',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            if (! $notification->uuid) {
                $notification->uuid = (string) Str::uuid();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('notify_at', '<=', now());
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
