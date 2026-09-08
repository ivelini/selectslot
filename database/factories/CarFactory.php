<?php

namespace Database\Factories;

use App\Enums\CarTypeEnum;
use App\Models\Car;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plate' => fake()->regexify('[АВЕКМНОРСТУХ][0-9]{3}[АВЕКМНОРСТУХ]{2}[0-9]{2}'),
            'radius' => fake()->numberBetween(13, 18),
            'car_type' => fake()->randomElement(CarTypeEnum::cases()),
            'has_runflat' => fake()->boolean(15),
            'has_tpms' => fake()->boolean(20),
        ];
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(fn () => ['customer_id' => $customerId]);
    }
}
