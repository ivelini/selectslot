<?php

namespace Tests\Unit\Models\Booking;

use App\Models\Booking\Booking;
use App\Models\Booking\BookingCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_code_finds_booking_created_with_code(): void
    {
        $code = BookingCode::create(['phone' => '79001234567', 'code_hash' => 'test-hash']);
        $booking = Booking::factory()->create(['booking_code_id' => $code->id]);

        $this->assertTrue($booking->is(Booking::forCode($code)));
    }

    public function test_for_code_returns_null_when_code_has_no_booking(): void
    {
        $code = BookingCode::create(['phone' => '79001234567', 'code_hash' => 'test-hash']);

        $this->assertNull(Booking::forCode($code));
    }
}
