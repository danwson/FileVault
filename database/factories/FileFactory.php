<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().'.txt';

        return [
            'user_id' => User::factory(),
            'original_name' => $name,
            'mime_type' => 'text/plain',
            'size' => fake()->numberBetween(100, 5000),
            'storage_path' => 'files/'.Str::uuid().'-'.$name,
        ];
    }
}
