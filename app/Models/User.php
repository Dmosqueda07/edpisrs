<?php

namespace App\Models;

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'division',
        'status',
        'signature_path',
        'reset_code',
        'reset_expires_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'division' => Division::class,
            'status' => UserStatus::class,
            'reset_code' => 'hashed',
            'reset_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeTechnicians(Builder $query): Builder
    {
        return $query
            ->where('division', Division::EDP->value)
            ->where('status', UserStatus::Active->value)
            ->where('is_active', true);
    }

    public function itSupportRequests(): HasMany
    {
        return $this->hasMany(ItSupportRequest::class, 'requester_user_id', 'user_id');
    }

    public function assignedItSupportRequests(): HasMany
    {
        return $this->hasMany(ItSupportRequest::class, 'assigned_technician_id', 'user_id');
    }

    public function resolvedItSupportRequests(): HasMany
    {
        return $this->hasMany(ItSupportRequest::class, 'resolved_by_user_id', 'user_id');
    }

    public function itSupportRequestApprovals(): HasMany
    {
        return $this->hasMany(ItSupportRequestApproval::class, 'approver_user_id', 'user_id');
    }

    public function itSupportRequestComments(): HasMany
    {
        return $this->hasMany(ItSupportRequestComment::class, 'user_id', 'user_id');
    }

    public function uploadedSupportRequestAttachments(): HasMany
    {
        return $this->hasMany(ItSupportRequestAttachment::class, 'uploaded_by', 'user_id');
    }

    public function itSupportRequestLogs(): HasMany
    {
        return $this->hasMany(ItSupportRequestLog::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
