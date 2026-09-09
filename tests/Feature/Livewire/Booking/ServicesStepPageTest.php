<?php

namespace Tests\Feature\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Livewire\Booking\SelectionStepPage\ServicesStepPage;
use App\Models\Service\ComplexService;
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

    /** @param  list<Service>  $services */
    private function complex(string $name, array $services): ComplexService
    {
        $complex = ComplexService::create(['name' => $name]);
        $complex->services()->sync(collect($services)->pluck('id'));

        return $complex;
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

    public function test_select_complex_adds_its_services(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $complex = $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        Livewire::withQueryParams(['date' => '2026-09-10', 'time' => '11:00'])
            ->test(ServicesStepPage::class)
            ->call('toggleComplex', $complex->id)
            ->assertSet('serviceIds', [$mounting->id, $balancing->id])
            ->assertSet('quantities', [$mounting->id => 4, $balancing->id => 4]);
    }

    public function test_deselect_full_complex_keeps_other_services(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $valve = $this->service('Замена вентиля', 5000);
        $complex = $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id, $balancing->id, $valve->id],
            'quantities' => [$mounting->id => 4, $balancing->id => 4, $valve->id => 1],
        ])
            ->test(ServicesStepPage::class)
            ->call('toggleComplex', $complex->id)
            ->assertSet('serviceIds', [$valve->id])
            ->assertSet('quantities', [$valve->id => 1]);
    }

    public function test_complex_sets_all_its_services_to_four(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $complex = $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$balancing->id],
            'quantities' => [$balancing->id => 1],
        ])
            ->test(ServicesStepPage::class)
            ->call('toggleComplex', $complex->id)
            ->assertSet('serviceIds', [$balancing->id, $mounting->id])
            ->assertSet('quantities', [$balancing->id => 4, $mounting->id => 4]);
    }

    public function test_complex_click_resets_quantities_to_four(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $complex = $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id, $balancing->id],
            'quantities' => [$mounting->id => 4, $balancing->id => 1],
        ])
            ->test(ServicesStepPage::class)
            ->call('toggleComplex', $complex->id)
            ->assertSet('serviceIds', [$mounting->id, $balancing->id])
            ->assertSet('quantities', [$mounting->id => 4, $balancing->id => 4]);
    }

    public function test_complex_state_follows_selection(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $complex = $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        Livewire::withQueryParams(['date' => '2026-09-10', 'time' => '11:00'])
            ->test(ServicesStepPage::class)
            ->assertDontSee('complex-card--full')
            ->assertDontSee('complex-card--partial')
            ->call('toggleService', $mounting->id)
            ->assertSee('complex-card--partial')
            ->call('toggleService', $balancing->id)
            ->assertSee('complex-card--full')
            ->assertDontSee('complex-card--partial');
    }

    public function test_complex_not_in_url_selection_survives_restore(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $this->rule($balancing, 13, CarTypeEnum::Passenger, 14000);
        $this->complex('Сезонный шиномонтаж', [$mounting, $balancing]);

        // восстановление из query (F5) — пакетов в URL нет, только услуги
        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id, $balancing->id],
            'quantities' => [$mounting->id => 4, $balancing->id => 4],
            'radius' => 13,
            'car_type' => 'passenger',
        ])
            ->test(ServicesStepPage::class)
            ->assertSee('complex-card--full')
            ->assertSee(route('booking.details', [
                'date' => '2026-09-10',
                'time' => '11:00',
                'services' => [$mounting->id, $balancing->id],
                'quantities' => [$mounting->id => 4, $balancing->id => 4],
                'radius' => 13,
                'car_type' => 'passenger',
            ]))
            ->assertDontSee('complex=');
    }

    public function test_service_card_price_tracks_radius_and_type(): void
    {
        $this->travelTo('2026-09-09 10:30:00');
        $this->slot('2026-09-10', 11);
        $mounting = $this->service('Снятие и установка колёс', 15000); // база 150
        $this->rule($mounting, 13, CarTypeEnum::Crossover, 22000);
        $this->rule($mounting, 16, CarTypeEnum::Crossover, 30000);
        $balancing = $this->service('Балансировка колёс', 14000); // без правил — цена не зависит

        Livewire::withQueryParams([
            'date' => '2026-09-10',
            'time' => '11:00',
            'services' => [$mounting->id, $balancing->id],
            'quantities' => [$mounting->id => 4, $balancing->id => 4],
        ])
            ->test(ServicesStepPage::class)
            // без параметров: у работ — «от базы», у допработ — точная цена
            ->assertSee('от 150 ₽')
            ->assertSee('140 ₽')
            ->call('selectCarType', 'crossover')
            ->call('selectRadius', 13)
            ->assertSee('220 ₽') // карточка снятия: правило (R13, crossover)
            ->assertDontSee('от 150 ₽')
            ->call('selectRadius', 16)
            ->assertSee('300 ₽') // карточка пересчиталась под R16
            ->assertSee('140 ₽'); // допработа не изменилась
    }
}
