<?php

namespace Tests\Feature\Livewire\Booking;

use App\Livewire\Booking\TimeStepPage;
use App\Models\Setting;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimeStepPageTest extends TestCase
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

    public function test_no_auto_selected_day_on_first_visit(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 18);
        $this->slot('2026-09-09', 10);

        Livewire::test(TimeStepPage::class)
            ->assertSet('date', null)
            ->assertSet('time', null)
            ->assertDontSee('time-chip')
            ->assertDontSee('/booking/services');
    }

    public function test_restore_selection_from_query(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-11', 11);

        Livewire::withQueryParams(['date' => '2026-09-11', 'time' => '11:00'])
            ->test(TimeStepPage::class)
            ->assertSet('date', '2026-09-11')
            ->assertSet('time', '11:00')
            ->assertSee('time-chip--selected');
    }

    public function test_reset_invalid_date_from_query(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 18);

        Livewire::withQueryParams(['date' => '9999-99-99'])
            ->test(TimeStepPage::class)
            ->assertSet('date', null)
            ->assertDontSee('time-chip');

        // вчера — вне горизонта (прошлая дата)
        Livewire::withQueryParams(['date' => '2026-09-07'])
            ->test(TimeStepPage::class)
            ->assertSet('date', null);

        $this->get('/?date=9999-99-99')->assertOk();
    }

    public function test_drop_unavailable_time_from_query(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 13, closed: true); // закрытый слот
        $this->slot('2026-09-08', 10); // открытый, но час уже прошёл границу (10:00 < 11:30)

        Livewire::withQueryParams(['date' => '2026-09-08', 'time' => '13:00'])
            ->test(TimeStepPage::class)
            ->assertSet('date', '2026-09-08')
            ->assertSet('time', null);

        Livewire::withQueryParams(['date' => '2026-09-08', 'time' => '10:00'])
            ->test(TimeStepPage::class)
            ->assertSet('time', null);
    }

    public function test_next_step_link_contains_selection(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-09', 10);
        $this->slot('2026-09-09', 11);

        Livewire::test(TimeStepPage::class)
            ->assertDontSee('/booking/services')
            ->call('selectDate', '2026-09-09')
            ->assertSet('date', '2026-09-09')
            ->assertSee('time-chip')
            ->call('selectTime', '10:00')
            ->assertSet('time', '10:00')
            ->assertSee(route('booking.services', ['date' => '2026-09-09', 'time' => '10:00']));
    }

    public function test_closed_hour_not_selectable(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-09', 10);
        $this->slot('2026-09-09', 13, closed: true);

        Livewire::test(TimeStepPage::class)
            ->call('selectDate', '2026-09-09')
            ->call('selectTime', '13:00')
            ->assertSet('time', null)
            ->call('selectTime', '10:00')
            ->assertSet('time', '10:00');
    }

    public function test_root_renders_time_step_page(): void
    {
        $this->travelTo('2026-09-08 10:30:00');
        $this->slot('2026-09-08', 18);

        $this->get('/')
            ->assertOk()
            ->assertSee('Выберите дату и время');
    }
}
