<?php

namespace App\Events;

use App\Contracts\AccessLoggable;
use App\Enums\AccessEventType;
use App\Models\File;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileUploaded implements AccessLoggable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public File $file,
        public string $ipAddress,
        public ?string $userAgent,
    ) {}

    public function logAttributes(): array
    {
        return [
            'file_id' => $this->file->id,
            'share_link_id' => null,
            'event_type' => AccessEventType::Upload->value,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
