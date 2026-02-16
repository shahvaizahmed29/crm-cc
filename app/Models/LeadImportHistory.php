<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadImportHistory extends Model
{
    protected $fillable = [
        'uploaded_by',
        'default_status_id',
        'original_file_name',
        'original_file_path',
        'failed_rows_file_path',
        'total_rows',
        'created_rows',
        'skipped_rows',
        'failed_rows',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'created_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function defaultStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'default_status_id');
    }
}
