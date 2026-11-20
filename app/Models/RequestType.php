<?php

namespace App\Models;

use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory;

    protected $table = 'request_types';

    protected $primaryKey = 'request_types_id';

    protected $fillable = [
        'key',
        'name',
        'category',
        'remote_allowed',
        'follow_up_question',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'remote_allowed' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'request_types_id', 'request_types_id');
    }
}
