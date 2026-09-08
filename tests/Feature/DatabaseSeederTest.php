<?php

namespace Tests\Feature;

use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking\Booking;
use App\Models\ScheduleTemplate;
use App\Models\Service\ComplexService;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_database(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(2, User::count());
        $this->assertGreaterThanOrEqual(8, Service::count());
        $this->assertGreaterThan(0, PriceRule::count());
        $this->assertSame(7, ScheduleTemplate::count());
        $this->assertGreaterThanOrEqual(100, Slot::count());
        $this->assertGreaterThanOrEqual(10, Booking::count());
        $this->assertGreaterThan(0, Setting::count());
    }

    public function test_seed_bookings_carry_parameters_without_cars(): void
    {
        $this->seed();

        $this->assertFalse(Schema::hasTable('cars'));
        $this->assertGreaterThan(0, Booking::whereNotNull('radius')->whereNotNull('car_type')->count());
        $this->assertGreaterThan(0, Booking::whereNotNull('plate')->count());
    }

    public function test_seed_seasonal_complex_contains_four_works(): void
    {
        $this->seed();

        $complex = ComplexService::where('name', 'Сезонный шиномонтаж')->firstOrFail();
        $this->assertTrue($complex->is_active);
        $this->assertSame(
            ['Снятие и установка колёс', 'Демонтаж колёс', 'Монтаж колёс', 'Балансировка колёс'],
            $complex->services()->orderBy('services.id')->pluck('name')->all(),
        );
    }

    public function test_seed_price_cube_by_real_price_list(): void
    {
        $this->seed();

        $mounting = Service::where('name', 'Снятие и установка колёс')->firstOrFail();

        // 5 работ × R13–R21 (9) × 3 типа = 135 правил; цены прайса за 1 колесо (копейки)
        $this->assertSame(135, PriceRule::count());
        $this->assertSame(27, $mounting->priceRules()->count());
        $this->assertDatabaseHas('price_rules', [
            'service_id' => $mounting->id,
            'radius' => 13,
            'car_type' => CarTypeEnum::Passenger->value,
            'price' => 15000, // лёгковые, R12–R15
        ]);
        $this->assertDatabaseHas('price_rules', [
            'service_id' => $mounting->id,
            'radius' => 21,
            'car_type' => CarTypeEnum::Passenger->value,
            'price' => 38000, // лёгковые, R20–R21
        ]);
        $this->assertDatabaseHas('price_rules', [
            'service_id' => $mounting->id,
            'radius' => 16,
            'car_type' => CarTypeEnum::Crossover->value,
            'price' => 30000, // внедорожная колонка, R16–R17
        ]);
        $this->assertSame(0, PriceRule::where('car_type', CarTypeEnum::Truck)->count());
    }

    public function test_seed_extra_works_without_rules(): void
    {
        $this->seed();

        $this->assertSame(10, Service::count());

        $valve = Service::where('name', 'Замена вентиля')->firstOrFail();
        $this->assertSame(5000, $valve->base_price);
        $this->assertSame(0, $valve->priceRules()->count());

        $utilization = Service::where('name', 'Утилизация шины')->firstOrFail();
        $this->assertSame(20000, $utilization->base_price);

        // комплектные строки прайса («при покупке», «4 колеса») в каталог не заводим
        $this->assertSame(0, Service::where('name', 'like', '%при покупке%')->count());
        $this->assertNull(Service::where('name', 'like', '%4 колеса%')->first());
    }

    public function test_seeder_has_bookings_around_today_with_history(): void
    {
        $this->seed();

        $today = now()->toDateString();

        $this->assertGreaterThan(0, Booking::where('status', BookingStatusEnum::Done)
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '<', $today))
            ->count());

        $this->assertGreaterThan(0, Booking::where('status', BookingStatusEnum::Confirmed)
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $today))
            ->count());
    }
}
