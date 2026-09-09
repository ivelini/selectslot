<?php

namespace Tests\Unit\Support;

use App\Enums\CarTypeEnum;
use App\Support\BookingQuery;
use Tests\TestCase;

class BookingQueryTest extends TestCase
{
    public function test_parse_date_accepts_iso_date(): void
    {
        $date = BookingQuery::parseDate('2026-09-10');

        $this->assertNotNull($date);
        $this->assertSame('2026-09-10', $date->format('Y-m-d'));
    }

    public function test_parse_date_rejects_garbage_and_overflow(): void
    {
        // переполнение даты (9999-99-99) отсекается round-trip'ом, не только регэкспом
        $this->assertNull(BookingQuery::parseDate('2026-99-99'));
        $this->assertNull(BookingQuery::parseDate('10.09.2026'));
        $this->assertNull(BookingQuery::parseDate('2026-9-10'));
        $this->assertNull(BookingQuery::parseDate(null));
    }

    public function test_parse_time_hour_accepts_whole_hour(): void
    {
        $this->assertSame(11, BookingQuery::parseTimeHour('11:00'));
        $this->assertSame(0, BookingQuery::parseTimeHour('00:00'));
        $this->assertSame(23, BookingQuery::parseTimeHour('23:00'));
    }

    public function test_parse_time_hour_rejects_minutes_and_out_of_range(): void
    {
        // сайт даёт целые часы: 12:30 из query не является временем шага
        $this->assertNull(BookingQuery::parseTimeHour('12:30'));
        $this->assertNull(BookingQuery::parseTimeHour('25:00'));
        $this->assertNull(BookingQuery::parseTimeHour('1100'));
        $this->assertNull(BookingQuery::parseTimeHour(null));
    }

    public function test_normalize_quantities_defaults_missing_to_four(): void
    {
        $this->assertSame([3 => 4], BookingQuery::normalizeQuantities([3], []));
    }

    public function test_normalize_quantities_clamps_to_bounds(): void
    {
        $normalized = BookingQuery::normalizeQuantities([1, 2], [1 => 0, 2 => 9]);

        $this->assertSame([1 => 1, 2 => 4], $normalized);
    }

    public function test_normalize_quantities_drops_stale_service_ids(): void
    {
        $this->assertSame([1 => 2], BookingQuery::normalizeQuantities([1], [1 => 2, 99 => 4]));
    }

    public function test_car_type_value_accepts_bookable_type(): void
    {
        $this->assertSame('passenger', BookingQuery::carTypeValueOrNull(CarTypeEnum::Passenger->value));
    }

    public function test_car_type_value_rejects_truck_and_garbage(): void
    {
        // грузовик — запись по звонку (ФТ-4), на сайте не выбирается
        $this->assertNull(BookingQuery::carTypeValueOrNull(CarTypeEnum::Truck->value));
        $this->assertNull(BookingQuery::carTypeValueOrNull('van'));
        $this->assertNull(BookingQuery::carTypeValueOrNull(null));
    }
}
