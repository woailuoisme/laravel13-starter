<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpRecord>
 */
class OtpRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'identifier' => fake()->unique()->safeEmail(),
            'type' => 'email',
            'action' => fake()->randomElement(['register', 'login', 'reset_password']),
            'code' => (string) fake()->numberBetween(100000, 999999),
            'used_at' => null,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function unused(): static
    {
        return $this->state(fn (): array => [
            'used_at' => null,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (): array => [
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
