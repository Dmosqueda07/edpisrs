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

    protected $fillable = [
        'key',
        'name',
        'category',
        'remote_allowed',
        'follow_up_question',
        'requires_approval',
        'approval_roles',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'remote_allowed' => 'boolean',
            'approval_roles' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function itSupportRequests(): HasMany
    {
        return $this->hasMany(ItSupportRequest::class);
    }
}
