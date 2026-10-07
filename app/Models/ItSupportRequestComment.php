<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSupportRequestComment extends Model
{
    protected $fillable = [
        'it_support_request_id',
        'user_id',
        'author_name',
        'body',
        'proof_path',
    ];

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
