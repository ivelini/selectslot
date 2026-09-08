<?php

namespace Database\Factories;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking\Booking;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
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

        $customer = Customer::factory()->create();
        $car = Car::factory()->create(['customer_id' => $customer->id]);

        return [
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'slot_id' => $slot->id,
            'start_time' => sprintf('%02d:00:00', $slot->hour),
            'status' => BookingStatusEnum::Confirmed,
            'source' => BookingSourceEnum::Site,
            'radius' => $car->radius,
            'car_type' => $car->car_type ?? CarTypeEnum::Passenger,
            'has_runflat' => $car->has_runflat,
            'has_tpms' => $car->has_tpms,
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
