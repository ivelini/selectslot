<?php

namespace Tests\Feature;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\PriceRule;
use App\Models\ScheduleTemplate;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertGreaterThan(0, Slot::where('is_closed', true)->count(), 'обеды закрыты');
        $this->assertGreaterThanOrEqual(10, Booking::count());
        $this->assertGreaterThan(0, Setting::count());
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
