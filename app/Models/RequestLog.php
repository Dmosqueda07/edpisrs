<?php

namespace App\Models;

use Database\Factories\RequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RequestLog extends Model
{
    /** @use HasFactory<RequestLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'request_logs';

    protected $primaryKey = 'logs_id';

    protected $fillable = [
        'requests_id',
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

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'requests_id', 'requests_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
