<?php

namespace App\Models;

use Database\Factories\ItSupportRequestDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSupportRequestDetail extends Model
{
    /** @use HasFactory<ItSupportRequestDetailFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'it_support_request_id',
        'field_key',
        'field_label',
        'field_value',
    ];

    public function itSupportRequest(): BelongsTo
    {
        return $this->belongsTo(ItSupportRequest::class);
    }
}
