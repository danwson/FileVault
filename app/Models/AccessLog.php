<?php

namespace App\Models;

use App\Enums\AccessEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    use HasFactory;

    // Log é imutável — só created_at faz sentido, não há "updated_at".
    const UPDATED_AT = null;

    protected $fillable = [
        'file_id',
        'share_link_id',
        'event_type',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AccessEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ShareLink::class);
    }
}
