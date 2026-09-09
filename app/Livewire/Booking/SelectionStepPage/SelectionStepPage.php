<?php

namespace App\Livewire\Booking\SelectionStepPage;

use App\Enums\WheelRadiusEnum;
use App\Models\Service\Service;
use App\Services\SlotAvailabilityReader;
use App\Support\BookingQuery;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Каркас шагов записи, несущих выбор (шаги 2–4, ADR 0006): выбор зеркалится
 * в query (?date=&time=&services[]=&quantities[id]=&radius=&car_type=).
 *
 * mount приводится к единому контракту: время не выбираемо (мусор/прошлое/
 * закрытый слот) → редирект на шаг 1; выбор нормализуется (активные услуги,
 * количества 1–4, валидные радиус/тип); дальше — специфика шага в afterMount().
 * Повторная проверка на submit-фазе — selectableDateTime() (время могло устареть).
 */
abstract class SelectionStepPage extends Component
{
    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    /** @var list<int> id выбранных услуг */
    #[Url(as: 'services')]
    public array $serviceIds = [];

    /** @var array<int, int> service_id => количество 1–4 */
    #[Url(as: 'quantities')]
    public array $quantities = [];

    #[Url]
    public ?int $radius = null;

    #[Url(as: 'car_type')]
    public ?string $carType = null;

    public function mount(SlotAvailabilityReader $reader): void
    {
        // Шаг осмыслен только с выбираемым временем: мусор/прошлое/закрытый слот — перевыбрать на шаге 1
        if ($this->selectableDateTime($reader) === null) {
            $this->redirect(route('booking.time'));

            return;
        }

        $this->serviceIds = Service::query()->activeByIds($this->serviceIds)->orderBy('id')->pluck('id')->all();
        $this->quantities = BookingQuery::normalizeQuantities($this->serviceIds, $this->quantities);
        $this->radius = $this->radius !== null ? WheelRadiusEnum::tryFrom($this->radius)?->value : null;
        $this->carType = BookingQuery::carTypeValueOrNull($this->carType);

        $this->afterMount();
    }

    /** Специфика шага после нормализации выбора (например, чтение черновика). */
    protected function afterMount(): void {}

    /**
     * Выбранные дата/час по формату query («Y-m-d»/«HH:00»): мусор → null.
     *
     * @return null|array{date: CarbonImmutable, hour: int}
     */
    protected function selectionDateTime(): ?array
    {
        $date = BookingQuery::parseDate($this->date);
        $hour = BookingQuery::parseTimeHour($this->time);

        return $date === null || $hour === null ? null : ['date' => $date, 'hour' => $hour];
    }

    /**
     * Выбранное время, если оно всё ещё доступно для записи: формат + правило
     * доступности (окно + открытый слот). На submit-фазе время могло устареть.
     *
     * @return null|array{date: CarbonImmutable, hour: int}
     */
    protected function selectableDateTime(SlotAvailabilityReader $reader): ?array
    {
        $selected = $this->selectionDateTime();
        if ($selected === null
            || ! $reader->isWithinBookingWindow($selected['date'])
            || ! $reader->isSelectableHour($selected['date'], $selected['hour'])) {
            return null;
        }

        return $selected;
    }

    /** Query-контракт выбора для route(): date/time/services/quantities/radius/car_type. */
    protected function selectionQueryParams(): array
    {
        return [
            'date' => $this->date,
            'time' => $this->time,
            'services' => $this->serviceIds,
            'quantities' => $this->quantities,
            'radius' => $this->radius,
            'car_type' => $this->carType,
        ];
    }
}
