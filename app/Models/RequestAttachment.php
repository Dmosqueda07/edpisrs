<?php

namespace App\Models;

use Database\Factories\RequestAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAttachment extends Model
{
    /** @use HasFactory<RequestAttachmentFactory> */
    use HasFactory;

    protected $table = 'request_attachments';

    protected $primaryKey = 'attachments_id';

    protected $fillable = [
        'requests_id',
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

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class, 'requests_id', 'requests_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
