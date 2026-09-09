<?php

namespace Tests\Feature\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Jobs\SendBookingCodeSms;
use App\Livewire\Booking\SelectionStepPage\CodeStepPage;
use App\Models\Booking\Booking;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\BookingCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CodeStepPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'min_lead_time_h', 'value' => '1']);
        Setting::create(['key' => 'booking_horizon_days', 'value' => '30']);
        Setting::create(['key' => 'reservation_timeout_min', 'value' => '15']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function slot(string $date, int $hour, bool $closed = false): void
    {
        Slot::create(['date' => $date, 'hour' => $hour, 'is_closed' => $closed]);
    }

    private function serviceWithRule(): Service
    {
        $service = Service::create([
            'name' => 'Снятие и установка колёс',
            'category' => 'tire',
            'is_active' => true,
            'base_price' => 15000,
        ]);
        PriceRule::create([
            'service_id' => $service->id,
            'radius' => 13,
            'car_type' => CarTypeEnum::Passenger,
            'price' => 15000,
        ]);

        return $service;
    }

    private function params(Service $service): array
    {
        return [
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$service->id],
            'quantities' => [$service->id => 4],
            'radius' => 13,
            'car_type' => 'passenger',
        ];
    }

    private function withDraft(): void
    {
        session(['booking_draft' => ['name' => 'Иван', 'phone' => '79001234567', 'plate' => null]]);
    }

    /** Возвращает компонент с полным окружением (слот, draft, query) и код. */
    private function readyComponent(Service $service): array
    {
        $this->travelTo('2026-09-09 10:00:00');
        $this->slot('2026-09-10', 11);
        $this->withDraft();
        $code = app(BookingCodeService::class)->issue('79001234567');

        $component = Livewire::withQueryParams($this->params($service))->test(CodeStepPage::class);

        return [$component, $code];
    }

    private function enterCode($component, string $code): mixed
    {
        $digits = str_split($code);

        return $component->set('digits', $digits)->call('submit');
    }

    public function test_redirects_to_details_without_draft(): void
    {
        $this->travelTo('2026-09-09 10:00:00');
        $this->slot('2026-09-10', 11);
        $service = $this->serviceWithRule();

        Livewire::withQueryParams($this->params($service))
            ->test(CodeStepPage::class)
            ->assertRedirect(route('booking.details', $this->params($service)));
    }

    public function test_wrong_code_shows_error(): void
    {
        $service = $this->serviceWithRule();
        [$component] = $this->readyComponent($service);

        $this->enterCode($component, '0000')
            ->assertHasErrors('code');

        $this->assertSame(0, Booking::count());
    }

    public function test_valid_code_creates_booking_and_redirects(): void
    {
        $service = $this->serviceWithRule();
        [$component, $code] = $this->readyComponent($service);

        $this->enterCode($component, $code)
            ->assertRedirect(route('booking.success'));

        $this->assertSame(1, Booking::count());
        $this->assertNull(session('booking_draft'));
    }

    public function test_slot_closed_redirects_to_unavailable(): void
    {
        $service = $this->serviceWithRule();
        [$component, $code] = $this->readyComponent($service);

        Slot::query()->where('hour', 11)->firstOrFail()->update(['is_closed' => true]);

        $this->enterCode($component, $code)
            ->assertRedirect(route('booking.unavailable'));

        $this->assertSame(0, Booking::count());
    }

    public function test_expired_code_redirects_to_expired_page(): void
    {
        $service = $this->serviceWithRule();
        [$component, $code] = $this->readyComponent($service);

        $this->travelTo('2026-09-09 10:16:00'); // +16 мин > TTL 15

        $this->enterCode($component, $code)
            ->assertRedirect(route('booking.code-expired'));
    }

    public function test_resend_limited_by_cooldown(): void
    {
        $service = $this->serviceWithRule();
        [$component] = $this->readyComponent($service);

        Queue::fake();

        // сразу после выдачи — кулдаун 60 с
        $component->call('resend')
            ->assertHasErrors('resend');
        Queue::assertNothingPushed();

        // спустя 60+ секунд — новый код отправлен
        $this->travelTo('2026-09-09 10:02:00');
        $component->call('resend');
        Queue::assertPushed(SendBookingCodeSms::class, fn ($job) => $job->phone === '79001234567');
    }
}
