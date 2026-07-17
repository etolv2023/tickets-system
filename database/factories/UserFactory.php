<?php

namespace Database\Factories;

use App\Enums\UserSkill;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::where('key', 'support')->value('id'),
            'skills' => UserSkill::None,
            'daily_capacity_hours' => 6.00,
            'must_change_password' => false,
            'is_active' => true,
        ];
    }

    public function role(string $key): static
    {
        return $this->state(fn () => ['role_id' => Role::where('key', $key)->value('id')]);
    }

    public function skills(UserSkill $skills): static
    {
        return $this->state(fn () => ['skills' => $skills]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
