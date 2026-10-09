<?php

namespace App\Models;

use Database\Factories\ItSupportRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ItSupportRequestLog extends Model
{
    /** @use HasFactory<ItSupportRequestLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'it_support_request_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'remarks',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });

        static::updating(fn () => throw new LogicException('Request audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Request audit logs are append-only.'));
    }

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
