<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Models\Slot;
use App\Services\SlotAvailabilityReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotAvailabilityReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'min_lead_time_h', 'value' => '1']);
        Setting::create(['key' => 'booking_horizon_days', 'value' => '30']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function reader(): SlotAvailabilityReader
    {
        return app(SlotAvailabilityReader::class);
    }

    private function slot(string $date, int $hour, bool $closed = false): void
    {
        Slot::create(['date' => $date, 'hour' => $hour, 'is_closed' => $closed]);
    }

    public function test_allow_slot_starting_exactly_at_lead_edge(): void
    {
        $this->travelTo('2026-09-08 10:00:00');
        $this->slot('2026-09-08', 11); // начало 11:00 == now + 1 ч

        $this->assertSame(
            [['hour' => 11, 'is_closed' => false]],
            $this->reader()->daySlots(CarbonImmutable::parse('2026-09-08')),
        );
    }

    public function test_hide_past_and_show_closed_hours(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 9);
        $this->slot('2026-09-08', 11); // начало 11:00 < 11:30 — прошедший
        $this->slot('2026-09-08', 13, closed: true);

        $this->assertSame(
            [['hour' => 13, 'is_closed' => true]],
            $this->reader()->daySlots(CarbonImmutable::parse('2026-09-08')),
        );
    }

    public function test_closed_hour_marked_busy_not_available(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-09', 10);
        $this->slot('2026-09-09', 11);
        $this->slot('2026-09-09', 13, closed: true);

        $slots = $this->reader()->daySlots(CarbonImmutable::parse('2026-09-09'));

        $this->assertSame([10, 11], collect($slots)->where('is_closed', false)->pluck('hour')->all());
        $this->assertContains(['hour' => 13, 'is_closed' => true], $slots);
    }

    public function test_exclude_slots_beyond_horizon(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-10-07', 10); // today + 29 — последняя доступная дата горизонта
        $this->slot('2026-10-08', 10); // today + 30 — за горизонтом

        $map = $this->reader()->daysWithAvailability(
            CarbonImmutable::parse('2026-09-08'),
            CarbonImmutable::parse('2026-10-07'),
        );

        $this->assertArrayNotHasKey('2026-10-08', $map);
        $this->assertSame(true, $map['2026-10-07']);
        $this->assertSame(
            [['hour' => 10, 'is_closed' => false]],
            $this->reader()->daySlots(CarbonImmutable::parse('2026-10-07')),
        );
        $this->assertSame([], $this->reader()->daySlots(CarbonImmutable::parse('2026-10-08')));
    }

    public function test_day_available_only_when_open_slot_in_window(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 10); // C: открытый, но час уже прошёл границу (10:00 < 11:30)
        $this->slot('2026-09-09', 14); // A: доступен
        $this->slot('2026-09-10', 13, closed: true); // B: только закрытый слот

        $map = $this->reader()->daysWithAvailability(
            CarbonImmutable::parse('2026-09-08'),
            CarbonImmutable::parse('2026-09-10'),
        );

        $this->assertSame([
            '2026-09-08' => false,
            '2026-09-09' => true,
            '2026-09-10' => false,
        ], $map);
    }
}
