<?php

namespace Tests\Unit\Models;

use App\Enums\Settings\SettingKeyEnum;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_stored_value(): void
    {
        Setting::create(['key' => 'booking_horizon_days', 'value' => '7']);

        $this->assertSame(7, Setting::get(SettingKeyEnum::HorizonDays));
    }

    public function test_get_returns_default_when_row_missing(): void
    {
        $this->assertSame(30, Setting::get(SettingKeyEnum::HorizonDays));
    }

    public function test_defaults_match_ft_parameters_table(): void
    {
        // таблица 7 ФТ: срок кода 15 мин, минимальное время 1 ч, горизонт 30 дней
        $this->assertSame(15, SettingKeyEnum::ReservationTimeoutMin->default());
        $this->assertSame(1, SettingKeyEnum::MinLeadTimeH->default());
        $this->assertSame(30, SettingKeyEnum::HorizonDays->default());
    }
}
