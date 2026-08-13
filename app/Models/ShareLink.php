<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'token',
        'expires_at',
        'max_uses',
        'access_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'access_count' => 'integer',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->access_count >= $this->max_uses;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted();
    }
}
