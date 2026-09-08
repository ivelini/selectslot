<?php

namespace Tests\Feature\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Livewire\Booking\ServicesStepPage;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Models\Setting;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServicesStepPageTest extends TestCase
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

    private function service(string $name, int $basePrice, bool $active = true): Service
    {
        return Service::create([
            'name' => $name,
            'category' => 'tire',
            'is_active' => $active,
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

    public function test_redirects_to_time_step_when_slot_unavailable(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-09', 13, closed: true);
        $this->slot('2026-09-09', 18);

        Livewire::withQueryParams(['date' => '2026-09-08', 'time' => '18:00'])
            ->test(ServicesStepPage::class)
            ->assertRedirect(route('booking.time'));

        Livewire::withQueryParams(['date' => '2026-09-09', 'time' => '13:00'])
            ->test(ServicesStepPage::class)
            ->assertRedirect(route('booking.time'));

        Livewire::withQueryParams(['date' => '2026-09-09'])
            ->test(ServicesStepPage::class)
            ->assertRedirect(route('booking.time'));
    }

    public function test_restore_full_selection_with_quantities_from_query(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id],
            'quantities' => [$mounting->id => 4],
            'radius' => 13,
            'car_type' => 'passenger',
        ])
            ->test(ServicesStepPage::class)
            ->assertSet('serviceIds', [$mounting->id])
            ->assertSet('quantities', [$mounting->id => 4])
            ->assertSet('radius', 13)
            ->assertSet('carType', 'passenger')
            ->assertSee('600 ₽');
    }

    public function test_drop_invalid_values_and_orphan_quantities(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $active = $this->service('Снятие и установка колёс', 15000);
        $inactive = $this->service('Скрытая', 50000, active: false);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$active->id, $inactive->id, 9999],
            'quantities' => [$active->id => 4, $inactive->id => 1, 9999 => 9],
            'radius' => 99,
            'car_type' => 'xyz',
        ])
            ->test(ServicesStepPage::class)
            ->assertSet('serviceIds', [$active->id])
            ->assertSet('quantities', [$active->id => 4])
            ->assertSet('radius', null)
            ->assertSet('carType', null);
    }

    public function test_quote_hidden_until_full_selection(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id],
            'quantities' => [$mounting->id => 4],
        ])
            ->test(ServicesStepPage::class)
            ->assertDontSee('booking-summary-total')
            ->assertDontSee('/booking/details')
            ->assertDontSee('RunFlat')
            ->assertDontSee('TPMS');
    }

    public function test_price_recalculates_on_radius_and_quantity_change(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $this->rule($mounting, 21, CarTypeEnum::Passenger, 38000);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id],
            'quantities' => [$mounting->id => 4],
            'radius' => 13,
            'car_type' => 'passenger',
        ])
            ->test(ServicesStepPage::class)
            ->assertSee('600 ₽')
            ->call('selectRadius', 21)
            ->assertSee('1 520 ₽')
            ->call('decrementQuantity', $mounting->id)
            ->call('decrementQuantity', $mounting->id)
            ->call('decrementQuantity', $mounting->id)
            ->assertSet('quantities', [$mounting->id => 1])
            ->assertSee('380 ₽')
            ->call('decrementQuantity', $mounting->id) // граница: меньше 1 нельзя
            ->assertSet('quantities', [$mounting->id => 1])
            ->call('incrementQuantity', $mounting->id)
            ->call('incrementQuantity', $mounting->id)
            ->call('incrementQuantity', $mounting->id)
            ->call('incrementQuantity', $mounting->id) // граница: больше 4 нельзя
            ->assertSet('quantities', [$mounting->id => 4]);
    }

    public function test_continue_link_carries_full_selection(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        $params = [
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id],
            'quantities' => [$mounting->id => 4],
            'radius' => 13,
            'car_type' => 'passenger',
        ];

        Livewire::withQueryParams($params)
            ->test(ServicesStepPage::class)
            ->assertSee(route('booking.details', $params))
            ->assertDontSee('Грузовик');

        unset($params['radius']);
        Livewire::withQueryParams($params)
            ->test(ServicesStepPage::class)
            ->assertDontSee('/booking/details');
    }

    public function test_missing_rule_shows_unavailable_message(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000); // правил на R14 нет

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id],
            'quantities' => [$mounting->id => 4],
            'radius' => 14,
            'car_type' => 'passenger',
        ])
            ->test(ServicesStepPage::class)
            ->assertSee('Цена недоступна')
            ->assertDontSee('/booking/details');
    }
}
