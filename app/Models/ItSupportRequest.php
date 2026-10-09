<?php

namespace App\Models;

use App\Enums\ItSupportRequestPriority;
use App\Enums\ItSupportRequestStatus;
use Database\Factories\ItSupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ItSupportRequest extends Model
{
    /** @use HasFactory<ItSupportRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'priority',
        'summary',
        'details',
        'requested_at',
        'closed_at',
        'resolution_notes',
        'requester_confirmed_at',
        'requester_user_id',
        'request_type_id',
        'assigned_technician_id',
        'requester_name',
        'division',
        'support_type',
        'follow_up_question',
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
            'priority' => ItSupportRequestPriority::class,
            'status' => ItSupportRequestStatus::class,
            'certified_at' => 'datetime',
            'requested_at' => 'datetime',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'requires_approval' => 'boolean',
            'approval_roles' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (! $request->ticket_no) {
                $request->ticket_no = self::nextTicketNumber();
            }
        });
    }

    private static function nextTicketNumber(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Ticket numbers must be allocated inside a request transaction.');
        }

        $year = now()->format('Y');

        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $lock = DB::selectOne(
                'DECLARE @lock_result int; EXEC @lock_result = sp_getapplock @Resource = ?, @LockMode = ?, @LockOwner = ?, @LockTimeout = ?; SELECT @lock_result AS lock_result;',
                ["it-support-request-ticket-{$year}", 'Exclusive', 'Transaction', 10000],
            );

            if (! $lock || $lock->lock_result < 0) {
                throw new RuntimeException("Unable to acquire the ticket-number sequence lock for {$year}.");
            }
        } else {
            ItSupportRequest::query()
                ->where('ticket_no', 'like', "EDP-{$year}-%")
                ->orderByDesc('ticket_no')
                ->lockForUpdate()
                ->first(['id']);
        }

        $latestTicket = DB::table('it_support_requests')
            ->where('ticket_no', 'like', "EDP-{$year}-%")
            ->orderByDesc('ticket_no')
            ->value('ticket_no');
        $sequence = $latestTicket ? (int) substr($latestTicket, -5) + 1 : 1;

        if ($sequence > 99999) {
            throw new RuntimeException("The ticket-number sequence for {$year} has reached its five-digit limit.");
        }

        return sprintf('EDP-%s-%05d', $year, $sequence);
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

    public function detailsRows(): HasMany
    {
        return $this->hasMany(ItSupportRequestDetail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ItSupportRequestAttachment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ItSupportRequestLog::class)->oldest();
    }
}
