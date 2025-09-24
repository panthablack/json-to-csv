<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvConfiguration extends Model
{
    protected $fillable = [
        'user_id',
        'json_data_id',
        'name',
        'description',
        'field_mappings',
        'column_order',
        'filters',
        'transformations',
        'include_headers',
        'delimiter',
        'enclosure',
        'escape'
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'column_order' => 'array',
        'filters' => 'array',
        'transformations' => 'array',
        'include_headers' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jsonData(): BelongsTo
    {
        return $this->belongsTo(JsonData::class);
    }

    public function getFieldMappingKeys(): array
    {
        return array_keys($this->field_mappings ?? []);
    }

    public function getFieldMappingValue(string $field): mixed
    {
        return $this->field_mappings[$field] ?? null;
    }

    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    public function hasTransformations(): bool
    {
        return !empty($this->transformations);
    }
}
