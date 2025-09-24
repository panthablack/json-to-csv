<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JsonData extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'filename',
        'original_filename',
        'file_size',
        'raw_data',
        'parsed_structure',
        'record_count',
        'status',
        'error_message'
    ];

    protected $casts = [
        'parsed_structure' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getParsedDataAttribute(): array
    {
        return json_decode($this->raw_data, true) ?? [];
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }
}
