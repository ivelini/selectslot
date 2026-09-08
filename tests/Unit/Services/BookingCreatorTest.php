<?php

namespace Tests\Unit\Services;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Enums\CodeStatusEnum;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking\Booking;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\BookingCodeService;
use App\Services\BookingCreator;
use App\ValueObjects\BookingSelection;
use App\ValueObjects\CustomerDraft;
use App\ValueObjects\VehicleParams;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'reservation_timeout_min', 'value' => '15']);
    }

    private function serviceWithRule(int $radius = 13, int $price = 15000): Service
    {
        $service = Service::create([
            'name' => 'Снятие и установка колёс',
            'category' => 'tire',
            'is_active' => true,
            'base_price' => $price,
        ]);
        PriceRule::create([
            'service_id' => $service->id,
            'radius' => $radius,
            'car_type' => CarTypeEnum::Passenger,
            'price' => $price,
        ]);

        return $service;
    }

    private function selection(Service $service, string $date = '2026-09-10', int $hour = 11): BookingSelection
    {
        return new BookingSelection(
            date: $date,
            hour: $hour,
            params: new VehicleParams(13, CarTypeEnum::Passenger),
            quantities: [$service->id => 4],
        );
    }

    private function draft(): CustomerDraft
    {
        return new CustomerDraft('Иван', '79001234567', 'А 000 АА 174');
    }

    private function openSlot(string $date = '2026-09-10', int $hour = 11): Slot
    {
        return Slot::create(['date' => $date, 'hour' => $hour]);
    }

    public function test_confirm_creates_booking_with_snapshot(): void
    {
        $service = $this->serviceWithRule();
        $this->openSlot();
        $code = app(BookingCodeService::class)->issue('79001234567');
        $code = app(BookingCodeService::class)->verify('79001234567', $code)->code;

        $booking = app(BookingCreator::class)->confirm($code, $this->draft(), $this->selection($service));

        $this->assertSame(BookingStatusEnum::Confirmed, $booking->status);
        $this->assertSame(BookingSourceEnum::Site, $booking->source);
        $this->assertSame(13, $booking->radius);
        $this->assertSame(CarTypeEnum::Passenger, $booking->car_type);
        $this->assertSame('А 000 АА 174', $booking->plate);
        $this->assertSame(60000, $booking->total_price); // 150 × 4

        $item = $booking->items()->firstOrFail();
        $this->assertSame(15000, $item->price); // цена за единицу
        $this->assertSame(4, $item->quantity);

        $this->assertNotNull($code->fresh()->used_at);
    }

    public function test_confirm_fails_when_slot_closed(): void
    {
        $service = $this->serviceWithRule();
        $this->openSlot()->update(['is_closed' => true]);
        $code = app(BookingCodeService::class)->issue('79001234567');
        $code = app(BookingCodeService::class)->verify('79001234567', $code)->code;

        try {
            app(BookingCreator::class)->confirm($code, $this->draft(), $this->selection($service));
            $this->fail('Ожидалось SlotUnavailableException');
        } catch (SlotUnavailableException) {
            // ожидаемо
        }

        $this->assertSame(0, Booking::count());
        $this->assertNull($code->fresh()->used_at);
    }

    public function test_used_code_returns_existing_booking(): void
    {
        $service = $this->serviceWithRule();
        $this->openSlot();
        $plain = app(BookingCodeService::class)->issue('79001234567');
        $verification = app(BookingCodeService::class)->verify('79001234567', $plain);

        $first = app(BookingCreator::class)->confirm($verification->code, $this->draft(), $this->selection($service));

        // повторная проверка того же кода — статус Used, запись не дублируется
        $again = app(BookingCodeService::class)->verify('79001234567', $plain);
        $this->assertSame(CodeStatusEnum::Used, $again->status);
        $this->assertTrue($first->is(app(BookingCreator::class)->bookingForCode($again->code)));
        $this->assertSame(1, Booking::count());
    }
}
