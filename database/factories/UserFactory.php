<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'username' => Str::lower(Str::slug($name, '')).fake()->unique()->numberBetween(100, 9999),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'is_suspended' => false,
            'force_password_change' => false,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_suspended' => true]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
