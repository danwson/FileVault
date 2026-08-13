<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\ShareLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'token' => Str::random(40),
            'expires_at' => now()->addDay(),
            'max_uses' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
