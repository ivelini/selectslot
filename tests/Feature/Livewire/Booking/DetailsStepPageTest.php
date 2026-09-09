<?php

namespace Tests\Feature\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Jobs\SendBookingCodeSms;
use App\Livewire\Booking\SelectionStepPage\DetailsStepPage;
use App\Models\Booking\BookingCode;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DetailsStepPageTest extends TestCase
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

    private function slot(string $date, int $hour, bool $closed = false): void
    {
        Slot::create(['date' => $date, 'hour' => $hour, 'is_closed' => $closed]);
    }

    private function service(string $name, int $basePrice): Service
    {
        return Service::create([
            'name' => $name,
            'category' => 'tire',
            'is_active' => true,
            'base_price' => $basePrice,
        ]);
    }

    private function rule(Service $service, int $radius, CarTypeEnum $carType, int $price): void
    {
        PriceRule::create([
            'service_id' => $service->id,
            'radius' => $radius,
            'car_type' => $carType,
            'price' => $price,
        ]);
    }

    /** Полный выбор: слот 2026-09-10 11:00, «Снятие» R13 passenger ×4, цена 600 ₽. */
    private function fullSelectionParams(Service $service): array
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

    public function test_redirects_to_time_step_when_slot_unavailable(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-09', 13, closed: true);
        $this->slot('2026-09-09', 18);

        Livewire::withQueryParams(['date' => '2026-09-08', 'time' => '18:00'])
            ->test(DetailsStepPage::class)
            ->assertRedirect(route('booking.time'));

        Livewire::withQueryParams(['date' => '2026-09-09', 'time' => '13:00'])
            ->test(DetailsStepPage::class)
            ->assertRedirect(route('booking.time'));

        Livewire::withQueryParams(['date' => '2026-09-09'])
            ->test(DetailsStepPage::class)
            ->assertRedirect(route('booking.time'));
    }

    public function test_submit_invalid_does_not_issue_code(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        Livewire::withQueryParams($this->fullSelectionParams($mounting))
            ->test(DetailsStepPage::class)
            ->set('name', '')
            ->set('phone', '123')
            ->call('submit')
            ->assertHasErrors(['name', 'phone']);

        $this->assertSame(0, BookingCode::count());
    }

    public function test_submit_valid_issues_code_saves_draft_and_redirects(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $params = $this->fullSelectionParams($mounting);

        Queue::fake();

        Livewire::withQueryParams($params)
            ->test(DetailsStepPage::class)
            ->set('name', 'Иван')
            ->set('phone', '+7 (900) 123-45-67')
            ->set('plate', 'А 000 АА 174')
            ->call('submit')
            ->assertRedirect(route('booking.code', $params));

        $this->assertSame(1, BookingCode::count());
        $this->assertSame('79001234567', BookingCode::query()->firstOrFail()->phone);
        $this->assertSame(
            ['name' => 'Иван', 'phone' => '79001234567', 'plate' => 'А 000 АА 174'],
            session('booking_draft'),
        );
        Queue::assertPushed(SendBookingCodeSms::class, fn ($job) => $job->phone === '79001234567');
    }

    public function test_submit_when_slot_closed_shows_unavailable(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $slot = Slot::create(['date' => '2026-09-10', 'hour' => 11]);

        $component = Livewire::withQueryParams($this->fullSelectionParams($mounting))
            ->test(DetailsStepPage::class);

        $slot->update(['is_closed' => true]);

        $component
            ->set('name', 'Иван')
            ->set('phone', '+7 (900) 123-45-67')
            ->call('submit')
            ->assertHasErrors('slot')
            ->assertNoRedirect();

        $this->assertSame(0, BookingCode::count());
    }

    public function test_draft_from_session_prefills_form(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        session(['booking_draft' => ['name' => 'Мария', 'phone' => '79000000000', 'plate' => null]]);

        Livewire::withQueryParams($this->fullSelectionParams($mounting))
            ->test(DetailsStepPage::class)
            ->assertSet('name', 'Мария')
            ->assertSet('phone', '79000000000')
            ->assertSet('plate', null);
    }

    public function test_summary_shows_selection_total(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        Livewire::withQueryParams($this->fullSelectionParams($mounting))
            ->test(DetailsStepPage::class)
            ->assertSee('600 ₽')
            ->assertDontSee('param-chips')
            ->assertSee('Получить код');
    }
}
