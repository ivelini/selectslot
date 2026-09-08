<?php

namespace Database\Factories;

use App\Enums\ServiceCategoryEnum;
use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'category' => fake()->randomElement(ServiceCategoryEnum::cases()),
            'is_active' => true,
            'base_price' => fake()->numberBetween(300, 3000) * 100, // копейки
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
