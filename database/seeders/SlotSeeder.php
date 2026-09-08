<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Slot;
use App\Services\SlotGridGenerator;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        // ADR 0001: сетка генерируется тем же сервисом, что и планировщик (slots:generate)
        app(SlotGridGenerator::class)->generate();

        $this->closeLunchBreaks();
    }

    /**
     * Обед 13:00–14:00 — закрытие слотов 13:00 на горизонте (ФТ-16: перерыв — закрытие слота, не сущность).
     * В жизни оператор закрывает такие слоты вручную; сид демонстрирует механизм.
     */
    private function closeLunchBreaks(): void
    {
        $horizon = (int) Setting::find('booking_horizon_days')?->value ?? 30;

        Slot::whereBetween('date', [now()->toDateString(), now()->addDays($horizon - 1)->toDateString()])
            ->where('hour', 13)
            ->update(['is_closed' => true, 'close_reason' => 'Обед (13:00–14:00)']);
    }
}
