<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Database\Factories\ItSupportRequestApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSupportRequestApproval extends Model
{
    /** @use HasFactory<ItSupportRequestApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'it_support_request_id',
        'approver_user_id',
        'approver_name',
        'approver_role',
        'decision',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id', 'user_id');
    }
}
