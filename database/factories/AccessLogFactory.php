<?php

namespace Database\Factories;

use App\Enums\AccessEventType;
use App\Models\AccessLog;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'share_link_id' => null,
            'event_type' => AccessEventType::Upload->value,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
