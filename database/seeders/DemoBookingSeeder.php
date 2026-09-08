<?php

namespace Database\Seeders;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingService;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Slot;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Демо-данные «неделя вокруг today»: клиенты с авто и записи за прошлую неделю
 * (история: done/cancelled/no_show) и на неделю вперёд (confirmed).
 * Даты — относительно today: сид всегда актуален.
 */
class DemoBookingSeeder extends Seeder
{
    private const CUSTOMER_NAMES = [
        'Иван Петров', 'Мария Соколова', 'Алексей Ковалёв', 'Ольга Новикова',
        'Дмитрий Морозов', 'Наталья Волкова', 'Сергей Лебедев', 'Елена Козлова',
        'Андрей Павлов', 'Татьяна Семёнова', 'Николай Голубев', 'Анна Виноградова',
        'Павел Кузнецов', 'Юлия Ефимова', 'Максим Орлов', 'Светлана Титова',
        'Владимир Захаров', 'Ксения Беляева',
    ];

    public function run(): void
    {
        $services = Service::where('is_active', true)->get()->keyBy('name');
        $mounting = $services->get('Снятие/установка колёс');
        $balancing = $services->get('Балансировка колёс');

        $customers = $this->seedCustomers();

        $this->seedHistorySlots(); // прошлая неделя: строки сетки, которые генератор уже не трогает

        foreach (range(-7, 6) as $dayOffset) {
            $date = now()->addDays($dayOffset);
            if ($date->isSunday() || $date->isToday()) {
                continue; // сегодняшний день не заполняем: статусы дня разворачивает оператор
            }

            $bookingsCount = $date->isPast() ? rand(4, 7) : rand(3, 5);

            foreach (range(1, $bookingsCount) as $ignored) {
                $this->seedOneBooking($date, $customers, $mounting, $balancing);
            }
        }
    }

    /** @return Collection<int, Customer> */
    private function seedCustomers(): Collection
    {
        $usedPhones = [];
        $customers = collect();

        foreach (self::CUSTOMER_NAMES as $name) {
            do {
                $phone = '+7 9'.rand(100, 999).' '.rand(100, 999).'-'.rand(10, 99).'-'.rand(10, 99);
            } while (isset($usedPhones[$phone]));
            $usedPhones[$phone] = true;

            $customer = Customer::create(['name' => $name, 'phone' => $phone]);

            $carsCount = rand(1, 2);
            for ($i = 0; $i < $carsCount; $i++) {
                Car::create([
                    'customer_id' => $customer->id,
                    'plate' => $this->randomPlate(),
                    'radius' => rand(13, 18),
                    'car_type' => fake()->randomElement([CarTypeEnum::Passenger, CarTypeEnum::Passenger, CarTypeEnum::Crossover, CarTypeEnum::Crossover, CarTypeEnum::Suv]),
                    'has_runflat' => rand(1, 100) <= 15,
                    'has_tpms' => rand(1, 100) <= 20,
                ]);
            }

            $customers->push($customer);
        }

        return Customer::with('cars')->get();
    }

    /**
     * Прошлая неделя: генератор создаёт сетку только от today (в прошлое не пишет),
     * поэтому строки истории создаём явно — как их создавали бы записи из админки.
     */
    private function seedHistorySlots(): void
    {
        $rows = [];
        foreach (range(-7, -1) as $dayOffset) {
            $date = now()->addDays($dayOffset);
            if ($date->isSunday()) {
                continue;
            }
            foreach (range(9, 18) as $hour) {
                $rows[] = ['date' => $date->toDateString(), 'hour' => $hour];
            }
        }

        foreach ($rows as $row) {
            $exists = Slot::whereDate('date', $row['date'])->where('hour', $row['hour'])->exists();

            if (! $exists) {
                Slot::create(['date' => $row['date'], 'hour' => $row['hour']]);
            }
        }
    }

    private function seedOneBooking(
        CarbonInterface $date,
        Collection $customers,
        ?Service $mounting,
        ?Service $balancing,
    ): void {
        $customer = $customers->random();
        $car = $customer->cars->random();

        $workingHours = [10, 11, 12, 14, 15, 16, 17, 18]; // без 13:00 — обед, слоты закрыты (SlotSeeder)
        $hour = $workingHours[array_rand($workingHours)];
        $minute = $date->isFuture() ? 0 : rand(0, 3) * 15; // виджет — целые часы, админка — любое время
        $slot = Slot::whereDate('date', $date)->where('hour', $hour)->first()
            ?? Slot::create(['date' => $date->format('Y-m-d'), 'hour' => $hour]);

        $services = [];
        if ($mounting !== null && rand(1, 100) <= 80) {
            $services[] = $mounting;
        }
        if ($balancing !== null && $services !== [] && rand(1, 100) <= 70) {
            $services[] = $balancing;
        }
        if ($services === []) {
            return;
        }

        $status = $this->statusFor($date);
        $source = $minute === 0 ? BookingSourceEnum::Site : BookingSourceEnum::Admin;

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'slot_id' => $slot->id,
            'start_time' => sprintf('%02d:%02d:00', $hour, $minute),
            'status' => $status,
            'source' => $source,
            'cancel_reason' => $status === BookingStatusEnum::Cancelled ? 'Клиент отменил' : null,
            'radius' => $car->radius,
            'car_type' => $car->car_type ?? CarTypeEnum::Passenger,
            'has_runflat' => $car->has_runflat,
            'has_tpms' => $car->has_tpms,
            'total_price' => 0, // пересчитается ниже по составу
        ]);

        $total = 0;
        foreach ($services as $service) {
            $price = $this->priceFor($service, $car);
            $total += $price;
            BookingService::create([
                'booking_id' => $booking->id,
                'service_id' => $service->id,
                'price' => $price,
            ]);
        }
        $booking->update(['total_price' => $total]);
    }

    private function statusFor(CarbonInterface $date): BookingStatusEnum
    {
        if ($date->isFuture()) {
            return BookingStatusEnum::Confirmed;
        }

        $roll = rand(1, 100);

        if ($roll <= 10) {
            return BookingStatusEnum::NoShow;
        }

        if ($roll <= 20) {
            return BookingStatusEnum::Cancelled;
        }

        return BookingStatusEnum::Done;
    }

    /**
     * Цена строки по правилам (ФТ-2): точное совпадение → правило без опций → базовая цена услуги.
     * Демо-копия подбора — при реализации расчёт уйдёт в PricingService (единая точка, НФ-4).
     */
    private function priceFor(Service $service, Car $car): int
    {
        $rule = PriceRule::where('service_id', $service->id)
            ->where('radius', $car->radius)
            ->where('car_type', $car->car_type)
            ->where('has_runflat', $car->has_runflat)
            ->where('has_tpms', $car->has_tpms)
            ->first();

        if ($rule === null) {
            $rule = PriceRule::where('service_id', $service->id)
                ->where('radius', $car->radius)
                ->where('car_type', $car->car_type)
                ->where('has_runflat', false)
                ->where('has_tpms', false)
                ->first();
        }

        return $rule?->price ?? $service->base_price;
    }

    private function randomPlate(): string
    {
        $letters = 'АВЕКМНОРСТУХ';
        $letter = fn (): string => mb_substr($letters, rand(0, 11), 1);

        return $letter().rand(100, 999).$letter().$letter().rand(2, 99);
    }
}
