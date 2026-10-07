<?php

namespace App\Models;

use App\Enums\ItSupportRequestStatus;
use Database\Factories\ItSupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItSupportRequest extends Model
{
    /** @use HasFactory<ItSupportRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_user_id',
        'request_type_id',
        'assigned_technician_id',
        'requester_name',
        'division',
        'support_type',
        'follow_up_question',
        'details',
        'attachment_path',
        'certified_at',
        'status',
        'assigned_at',
        'started_at',
        'resolved_by_user_id',
        'resolved_at',
        'completed_at',
        'requires_approval',
        'approval_roles',
    ];

    protected function casts(): array
    {
        return [
            'status' => ItSupportRequestStatus::class,
            'certified_at' => 'datetime',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'completed_at' => 'datetime',
            'requires_approval' => 'boolean',
            'approval_roles' => 'array',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id', 'user_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id', 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id', 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItSupportRequestComment::class)->oldest();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ItSupportRequestApproval::class)->oldest();
    }
}
