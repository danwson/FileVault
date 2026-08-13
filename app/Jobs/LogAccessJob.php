<?php

namespace App\Jobs;

use App\Models\AccessLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array{file_id: int, share_link_id: ?int, event_type: string, ip_address: string, user_agent: ?string}  $attributes
     */
    public function __construct(public array $attributes) {}

    public function handle(): void
    {
        AccessLog::create($this->attributes);
    }
}
