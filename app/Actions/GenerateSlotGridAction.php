<?php

namespace App\Actions;

use App\Enums\Settings\SettingKeyEnum;
use App\Models\ScheduleTemplate;
use App\Models\Setting;
use App\Models\Slot;
use Carbon\CarbonImmutable;

/**
 * Генерация сетки слотов по шаблону недели (ADR 0001).
 *
 * Идемпотентна: существующие строки не трогает (сохраняет is_closed/close_reason/booking_id),
 * удаляет только открытые пустые строки вне актуального шаблона. Прошлое не пересматривает.
 */
class GenerateSlotGridAction
{
    public function handle(): void
    {
        $today = CarbonImmutable::today();
        $horizonDays = Setting::get(SettingKeyEnum::HorizonDays);
        $workingHoursByWeekday = $this->workingHoursByWeekday();

        $expected = [];
        foreach (range(0, $horizonDays - 1) as $offset) {
            $date = $today->addDays($offset);
            $weekday = $date->isoWeekday() - 1; // 0 (пн) – 6 (вс), как schedule_templates.weekday
            foreach ($workingHoursByWeekday[$weekday] ?? [] as $hour) {
                $expected["{$date->format('Y-m-d')} {$hour}"] = ['date' => $date->format('Y-m-d'), 'hour' => $hour];
            }
        }

        $this->upsertSlots($expected);
        $this->removeOutOfScheduleSlots($today->format('Y-m-d'), $expected);
    }

    /** @return array<int, list<int>> weekday (0 пн – 6 вс) => рабочие часы */
    private function workingHoursByWeekday(): array
    {
        $hours = [];
        foreach (ScheduleTemplate::all() as $template) {
            if ($template->open_time === null || $template->close_time === null) {
                continue; // выходной
            }
            $openHour = (int) substr($template->open_time, 0, 2);
            $closeHour = (int) substr($template->close_time, 0, 2);
            $hours[$template->weekday] = range($openHour, $closeHour - 1);
        }

        return $hours;
    }

    /** @param array<string, array{date: string, hour: int}> $expected */
    private function upsertSlots(array $expected): void
    {
        $now = now();
        $rows = array_map(
            fn (array $slot) => [
                'date' => $slot['date'],
                'hour' => $slot['hour'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_values($expected),
        );

        if ($rows !== []) {
            Slot::insertOrIgnore($rows);
        }
    }

    /**
     * Строки в окне горизонта, отсутствующие в ожидаемой сетке: удаляются только открытые и без записей.
     *
     * @param  array<string, array{date: string, hour: int}>  $expected
     */
    private function removeOutOfScheduleSlots(string $today, array $expected): void
    {
        $stale = Slot::where('date', '>=', $today)
            ->where('is_closed', false)
            ->whereDoesntHave('bookings')
            ->get()
            ->filter(fn (Slot $slot) => ! isset($expected["{$slot->date->format('Y-m-d')} {$slot->hour}"]));

        if ($stale->isNotEmpty()) {
            Slot::whereIn('id', $stale->pluck('id'))->delete();
        }
    }
}
