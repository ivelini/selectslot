<?php

namespace App\Services;

use App\Enums\Settings\SettingKeyEnum;
use App\Models\Setting;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Чтение сетки слотов для показа на сайте.
 *
 * Единый источник правила доступности (НФ-4): слот не закрыт, начало не раньше
 * now + min_lead_time_h (округление вверх до часа), дата в пределах горизонта
 * booking_horizon_days. Подтверждение кода (ФТ-8) проверит то же правило
 * с блокировкой строки — компонент сайта в таблицу напрямую не ходит.
 */
class SlotAvailabilityReader
{
    /**
     * Карта «Y-m-d => есть ли доступный слот» для всех дат диапазона [from..to] включительно.
     * Прошлые и выходящие за горизонт даты в карте — false: календарь не даёт по ним клик.
     *
     * @return array<string, bool>
     */
    public function daysWithAvailability(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $available = $this->availableDatesBetween($from, $to);

        $map = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $map[$date->format('Y-m-d')] = $available->contains($date->format('Y-m-d'));
        }

        return $map;
    }

    /**
     * Существующие строки слотов дня от первого доступного часа: прошедшие часы
     * дня не показываются вовсе, закрытые помечаются (сайт рисует их busy, ФТ-6).
     *
     * @return list<array{hour: int, is_closed: bool}>
     */
    /**
     * Можно ли записаться на час дня: слот существует, открыт и в границах окна.
     * Единая проверка для шагов «Время» и «Услуги» (время из query обязано оставаться выбираемым).
     */
    public function isSelectableHour(CarbonImmutable $date, int $hour): bool
    {
        return collect($this->daySlots($date))->contains(
            fn (array $slot): bool => $slot['hour'] === $hour && ! $slot['is_closed'],
        );
    }

    /**
     * Входит ли дата в окно записи: не прошлая и в пределах горизонта.
     */
    public function isWithinBookingWindow(CarbonImmutable $date): bool
    {
        $today = CarbonImmutable::today();

        return $date->gte($today) && $date->lte($today->addDays(Setting::get(SettingKeyEnum::HorizonDays) - 1));
    }

    public function daySlots(CarbonImmutable $date): array
    {
        $today = CarbonImmutable::today();
        $horizonLastDate = $today->addDays(Setting::get(SettingKeyEnum::HorizonDays) - 1);

        if ($date->lt($today) || $date->gt($horizonLastDate)) {
            return [];
        }

        return Slot::query()
            // whereDate, а не where: date-каст Eloquent хранит datetime-строку, sqlite чувствителен к формату
            ->whereDate('date', $date->toDateString())
            ->where('hour', '>=', $this->firstSelectableHourFor($date, $today))
            ->orderBy('hour')
            ->get(['hour', 'is_closed'])
            ->map(fn (Slot $slot): array => ['hour' => $slot->hour, 'is_closed' => $slot->is_closed])
            ->all();
    }

    /**
     * @return Collection<int, string> даты 'Y-m-d', где есть хотя бы один открытый слот в лимитах
     */
    private function availableDatesBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $today = CarbonImmutable::today();
        $query = Slot::query()
            ->where('is_closed', false)
            // whereDate, а не whereBetween: date-каст Eloquent хранит datetime-строку
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereDate('date', '>=', $today->toDateString()); // прошлое никогда не доступно

        // Граница min_lead касается только текущего дня: для остальных дат в диапазоне все часы в будущем.
        if ($from->lte($today) && $today->lte($to)) {
            $query->where(fn (Builder $q) => $q
                ->whereDate('date', '>', $today->toDateString())
                ->orWhere('hour', '>=', $this->firstSelectableHour()));
        }

        return $query->pluck('date')->map(fn ($date): string => $date->format('Y-m-d'));
    }

    private function firstSelectableHourFor(CarbonImmutable $date, CarbonImmutable $today): int
    {
        return $date->equalTo($today) ? $this->firstSelectableHour() : 0;
    }

    /**
     * Первый час, доступный для записи: начало слота должно быть >= now + min_lead.
     * Округление вверх до часа: 10:30 + 1 ч = 11:30 → доступен час 12:00, час 11:00 уже недоступен.
     */
    private function firstSelectableHour(): int
    {
        $edge = CarbonImmutable::now()->addHours(Setting::get(SettingKeyEnum::MinLeadTimeH));

        return $edge->startOfHour()->equalTo($edge) ? $edge->hour : $edge->hour + 1;
    }
}
