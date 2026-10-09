<?php

namespace App\Models;

use Database\Factories\ItSupportRequestCommentFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSupportRequestComment extends Model
{
    /** @use HasFactory<ItSupportRequestCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'it_support_request_id',
        'user_id',
        'author_name',
        'body',
        'proof_path',
        'is_internal',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
