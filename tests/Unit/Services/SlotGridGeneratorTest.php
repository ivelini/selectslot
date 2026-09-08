<?php

namespace Tests\Unit\Services;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ScheduleTemplate;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\SlotGridGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotGridGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = CarbonImmutable::today();
        Setting::create(['key' => 'booking_horizon_days', 'value' => '30']);
    }

    /** Шаблон: Пн–Сб 09:00–19:00, Вс — выходной. Возвращает число рабочих дней в [from, from + $days). */
    private function seedWeeklySchedule(): void
    {
        foreach (range(0, 6) as $weekday) {
            ScheduleTemplate::create([
                'weekday' => $weekday,
                'open_time' => $weekday === 6 ? null : '09:00:00',
                'close_time' => $weekday === 6 ? null : '19:00:00',
            ]);
        }
    }

    private function workingDaysInPeriod(int $days): int
    {
        $count = 0;
        foreach (range(0, $days - 1) as $offset) {
            if ($this->today->addDays($offset)->isoWeekday() !== 7) {
                $count++;
            }
        }

        return $count;
    }

    public function test_creates_slots_on_horizon_from_weekly_schedule(): void
    {
        $this->seedWeeklySchedule();

        app(SlotGridGenerator::class)->generate();

        $expectedTotal = $this->workingDaysInPeriod(30) * 10; // часы 09..18
        $this->assertSame($expectedTotal, Slot::count());
        $this->assertSame(0, Slot::where('is_closed', true)->count());

        $monday = $this->today->next(CarbonImmutable::MONDAY);
        $hours = Slot::where('date', $monday->toDateString())->orderBy('hour')->pluck('hour')->all();
        $this->assertSame(range(9, 18), $hours);
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->seedWeeklySchedule();
        $generator = app(SlotGridGenerator::class);

        $generator->generate();
        $firstCount = Slot::count();
        $closedAfterFirst = Slot::where('is_closed', true)->count();

        $generator->generate();

        $this->assertSame($firstCount, Slot::count());
        $this->assertSame($closedAfterFirst, Slot::where('is_closed', true)->count());
    }

    public function test_keeps_closed_slots_on_regeneration(): void
    {
        $this->seedWeeklySchedule();
        $generator = app(SlotGridGenerator::class);
        $generator->generate();

        $slot = Slot::where('date', $this->today->toDateString())->where('hour', 14)->firstOrFail();
        $slot->update(['is_closed' => true, 'close_reason' => 'обед']);

        $generator->generate();

        $slot->refresh();
        $this->assertTrue($slot->is_closed);
        $this->assertSame('обед', $slot->close_reason);
    }

    public function test_keeps_out_of_schedule_slot_with_booking(): void
    {
        $this->seedWeeklySchedule();
        app(SlotGridGenerator::class)->generate();

        // строка 20:00 вне шаблона (шаблон до 19:00), созданная записью из админки
        $slot = Slot::create(['date' => $this->today->toDateString(), 'hour' => 20]);
        $customer = Customer::create(['name' => 'Иван', 'phone' => '+7 900 000-00-00']);
        Booking::create([
            'customer_id' => $customer->id,
            'slot_id' => $slot->id,
            'start_time' => '20:00:00',
            'status' => BookingStatusEnum::Confirmed,
            'source' => BookingSourceEnum::Admin,
            'radius' => 16,
            'car_type' => CarTypeEnum::Passenger,
            'has_runflat' => false,
            'has_tpms' => false,
            'total_price' => 10000,
        ]);

        app(SlotGridGenerator::class)->generate();

        $this->assertDatabaseHas('slots', ['id' => $slot->id, 'hour' => 20]);
    }

    public function test_removes_empty_open_slot_outside_schedule(): void
    {
        $this->seedWeeklySchedule();
        app(SlotGridGenerator::class)->generate();

        $slot = Slot::create(['date' => $this->today->toDateString(), 'hour' => 20]);

        app(SlotGridGenerator::class)->generate();

        $this->assertDatabaseMissing('slots', ['id' => $slot->id]);
    }

    public function test_does_not_touch_past_slots(): void
    {
        $this->seedWeeklySchedule();
        app(SlotGridGenerator::class)->generate();

        $past = Slot::create(['date' => $this->today->subDay()->toDateString(), 'hour' => 20]);

        app(SlotGridGenerator::class)->generate();

        $this->assertDatabaseHas('slots', ['id' => $past->id]);
    }

    public function test_horizon_reads_from_settings(): void
    {
        $this->seedWeeklySchedule();
        Setting::where('key', 'booking_horizon_days')->update(['value' => '7']);

        app(SlotGridGenerator::class)->generate();

        $this->assertNull(Slot::where('date', $this->today->addDays(7)->toDateString())->first());
        $expectedTotal = $this->workingDaysInPeriod(7) * 10;
        $this->assertSame($expectedTotal, Slot::count());
    }
}
