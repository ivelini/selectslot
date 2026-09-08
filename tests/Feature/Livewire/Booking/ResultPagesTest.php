<?php

namespace Tests\Feature\Livewire\Booking;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingService;
use App\Models\Customer;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'booking_horizon_days', 'value' => '30']);
    }

    private function booking(): Booking
    {
        $customer = Customer::create(['name' => 'Иван', 'phone' => '79001234567']);
        $slot = Slot::create(['date' => '2026-09-10', 'hour' => 11]);
        $service = Service::create([
            'name' => 'Снятие и установка колёс',
            'category' => 'tire',
            'is_active' => true,
            'base_price' => 15000,
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'slot_id' => $slot->id,
            'start_time' => '11:00:00',
            'status' => BookingStatusEnum::Confirmed,
            'source' => BookingSourceEnum::Site,
            'radius' => 13,
            'car_type' => CarTypeEnum::Passenger,
            'total_price' => 60000,
        ]);
        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'price' => 15000,
            'quantity' => 4,
        ]);

        return $booking;
    }

    public function test_result_pages_require_session_marker(): void
    {
        $this->get(route('booking.success'))->assertRedirect(route('booking.time'));
        $this->get(route('booking.unavailable'))->assertRedirect(route('booking.time'));
        $this->get(route('booking.code-expired'))->assertRedirect(route('booking.time'));
    }

    public function test_success_page_shows_booking_details(): void
    {
        $booking = $this->booking();

        $this->withSession(['booking_success_id' => $booking->id])
            ->get(route('booking.success'))
            ->assertOk()
            ->assertSee('Запись подтверждена')
            ->assertSee('600 ₽');
    }

    public function test_unavailable_page_shows_message_and_action(): void
    {
        $this->withSession(['booking_unavailable' => true])
            ->get(route('booking.unavailable'))
            ->assertOk()
            ->assertSee('недоступно');
    }

    public function test_code_expired_page_shows_message(): void
    {
        $this->withSession(['booking_code_expired' => true])
            ->get(route('booking.code-expired'))
            ->assertOk()
            ->assertSee('Код устарел');
    }
}
