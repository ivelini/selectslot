<?php

namespace Database\Factories;

use App\Enums\Booking\BookingSourceEnum;
use App\Enums\Booking\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking\Booking;
use App\Models\Customer;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
#[UseModel(Booking::class)]
class BookingFactory extends Factory
{
    public function definition(): array
    {
        // слот из существующих строк сетки, иначе создаётся по требованию (как запись из админки)
        $slot = Slot::query()
            ->where('date', '>=', now()->toDateString())
            ->where('is_closed', false)
            ->inRandomOrder()
            ->first()
            ?? Slot::create(['date' => now()->addDays(rand(1, 20))->toDateString(), 'hour' => rand(9, 18)]);

        return [
            'customer_id' => Customer::factory(),
            'slot_id' => $slot->id,
            'start_time' => sprintf('%02d:00:00', $slot->hour),
            'status' => BookingStatusEnum::Confirmed,
            'source' => BookingSourceEnum::Site,
            'radius' => fake()->numberBetween(13, 21),
            'car_type' => fake()->randomElement(CarTypeEnum::bookable()),
            'plate' => fake()->boolean(70) ? fake()->regexify('[АВЕКМНОРСТУХ][0-9]{3}[АВЕКМНОРСТУХ]{2}[0-9]{2}') : null,
            'total_price' => 60000,
        ];
    }

    public function forSlot(Slot $slot): static
    {
        return $this->state(fn () => ['slot_id' => $slot->id, 'start_time' => sprintf('%02d:00:00', $slot->hour)]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => BookingStatusEnum::Done]);
    }

    public function cancelled(string $reason = 'клиент передумал'): static
    {
        return $this->state(fn () => ['status' => BookingStatusEnum::Cancelled, 'cancel_reason' => $reason]);
    }
}
