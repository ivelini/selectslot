<?php

namespace App\Livewire\Booking;

use App\Services\SlotAvailabilityReader;
use App\Support\RussianDate;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Шаг 1 «Время» (мокап index.html): календарь + сетка часов на выбранный день.
 *
 * Выбор зеркалится в query (?date=&time=): F5 и «назад» живы (ADR 0006). Чистый вход
 * день не выбирает — сетка часов пуста до клика по доступному дню календаря.
 */
#[Layout('layouts.public')]
class TimeStepPage extends Component
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):00$/';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    /** Открытый в календаре месяц «Y-m»; состояние просмотра — в URL не пишется. */
    public string $visibleMonth;

    public function mount(SlotAvailabilityReader $reader): void
    {
        $date = $this->parseDate($this->date);
        $this->date = $date !== null && $reader->isWithinBookingWindow($date) ? $date->format('Y-m-d') : null;

        // Невалидная дата тянет за собой время: время без дня ничего не значит
        $this->time = $this->date === null
            ? null
            : $this->selectableTimeOrNull($this->time, CarbonImmutable::parse($this->date), $reader);

        $this->visibleMonth = $this->date !== null
            ? substr($this->date, 0, 7)
            : CarbonImmutable::now()->format('Y-m');
    }

    public function selectDate(string $date, SlotAvailabilityReader $reader): void
    {
        $parsed = $this->parseDate($date);
        if ($parsed === null || ! $reader->isWithinBookingWindow($parsed)) {
            return;
        }

        // Кликабельны только дни с открытыми слотами; guard повторяет календарь (контракт на входе)
        $availability = $reader->daysWithAvailability($parsed, $parsed);
        if (! $availability[$parsed->format('Y-m-d')]) {
            return;
        }

        $this->date = $parsed->format('Y-m-d');
        $this->time = null; // прежнее время в новом дне может быть недоступно
        $this->visibleMonth = substr($this->date, 0, 7);
    }

    public function selectTime(string $time, SlotAvailabilityReader $reader): void
    {
        if ($this->date === null || preg_match(self::TIME_PATTERN, $time) !== 1) {
            return;
        }

        $hour = (int) substr($time, 0, 2);
        if (! $reader->isSelectableHour(CarbonImmutable::parse($this->date), $hour)) {
            return;
        }

        $this->time = $time;
    }

    public function previousMonth(): void
    {
        $prev = $this->visibleMonthDate()->subMonthNoOverflow();
        $todayMonth = CarbonImmutable::now()->format('Y-m');

        // Назад — не дальше месяца с сегодняшним днём: прошлые месяцы записи не предлагаются
        if ($prev->format('Y-m') < $todayMonth) {
            return;
        }

        $this->visibleMonth = $prev->format('Y-m');
    }

    public function nextMonth(SlotAvailabilityReader $reader): void
    {
        $next = $this->visibleMonthDate()->addMonthNoOverflow();

        if (! $reader->isWithinBookingWindow($next)) {
            return;
        }

        $this->visibleMonth = $next->format('Y-m');
    }

    public function render(SlotAvailabilityReader $reader): View
    {
        $month = $this->visibleMonthDate();
        $todayMonth = CarbonImmutable::now()->format('Y-m');

        return view('livewire.booking.time-step-page', [
            'calendar' => $this->calendarData($month, $reader),
            'monthTitle' => RussianDate::monthTitle($month),
            'prevMonthEnabled' => $this->visibleMonth > $todayMonth,
            'nextMonthEnabled' => $reader->isWithinBookingWindow($month->addMonthNoOverflow()),
            'timeSlots' => $this->dayTimeSlots($reader),
            'selectedDayLabel' => $this->selectedDayLabel(),
            'summaryDateLabel' => $this->summaryDateLabel(),
        ]);
    }

    /**
     * Данные календаря открытого месяца: ячейки готовы к отрисовке (классы собраны здесь,
     * blade не содержит логики). День кликабелен ⇐ есть открытый слот в лимитах.
     *
     * @return array{leadingEmpty: int, cells: list<array{date: string, classes: string, selectable: bool}>}
     */
    private function calendarData(CarbonImmutable $month, SlotAvailabilityReader $reader): array
    {
        $first = $month->startOfMonth();
        $last = $first->addDays($first->daysInMonth - 1);
        $today = CarbonImmutable::today();
        $availability = $reader->daysWithAvailability($first, $last);

        $cells = [];
        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = $first->addDays($day - 1);
            $key = $date->format('Y-m-d');
            $isPast = $date->lt($today);
            $selectable = ! $isPast && ($availability[$key] ?? false);

            $classes = 'calendar-day';
            $classes .= $isPast ? ' calendar-day--past' : ($selectable ? '' : ' calendar-day--off');
            if ($date->equalTo($today)) {
                $classes .= ' calendar-day--today';
            }
            if ($key === $this->date) {
                $classes .= ' calendar-day--selected';
            }

            $cells[] = ['date' => $key, 'classes' => $classes, 'selectable' => $selectable];
        }

        return ['leadingEmpty' => $first->dayOfWeekIso - 1, 'cells' => $cells];
    }

    /**
     * Часы выбранного дня: существующие строки слотов от первого доступного часа;
     * закрытые помечаются busy и не выбираются (ФТ-6).
     *
     * @return list<array{hour: int, label: string, is_closed: bool, selected: bool}>
     */
    private function dayTimeSlots(SlotAvailabilityReader $reader): array
    {
        if ($this->date === null) {
            return [];
        }

        $slots = $reader->daySlots(CarbonImmutable::parse($this->date));

        return array_map(function (array $slot): array {
            $label = sprintf('%02d:00', $slot['hour']);

            return [
                'hour' => $slot['hour'],
                'label' => $label,
                'is_closed' => $slot['is_closed'],
                'selected' => $label === $this->time,
            ];
        }, $slots);
    }

    private function selectedDayLabel(): ?string
    {
        if ($this->date === null) {
            return null;
        }

        return RussianDate::dayWithWeekday(CarbonImmutable::parse($this->date));
    }

    private function summaryDateLabel(): ?string
    {
        if ($this->date === null) {
            return null;
        }

        return RussianDate::dayShort(CarbonImmutable::parse($this->date));
    }

    /** Строгое чтение «Y-m-d»: мусор и переполнение дат (9999-99-99) отсекаются round-trip'ом. */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match(self::DATE_PATTERN, $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function selectableTimeOrNull(?string $value, CarbonImmutable $date, SlotAvailabilityReader $reader): ?string
    {
        if ($value === null || preg_match(self::TIME_PATTERN, $value) !== 1) {
            return null;
        }

        $hour = (int) substr($value, 0, 2);

        return $reader->isSelectableHour($date, $hour) ? $value : null;
    }

    private function visibleMonthDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->visibleMonth);
    }
}
