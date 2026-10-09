<?php

namespace App\Models;

use Database\Factories\ItSupportRequestAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSupportRequestAttachment extends Model
{
    /** @use HasFactory<ItSupportRequestAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'it_support_request_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
