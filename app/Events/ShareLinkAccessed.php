<?php

namespace App\Events;

use App\Contracts\AccessLoggable;
use App\Enums\AccessEventType;
use App\Models\ShareLink;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShareLinkAccessed implements AccessLoggable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ShareLink $shareLink,
        public string $ipAddress,
        public ?string $userAgent,
    ) {}

    public function logAttributes(): array
    {
        return [
            'file_id' => $this->shareLink->file_id,
            'share_link_id' => $this->shareLink->id,
            'event_type' => AccessEventType::ShareAccess->value,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
