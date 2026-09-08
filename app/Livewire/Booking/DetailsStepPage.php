<?php

namespace App\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Enums\WheelRadiusEnum;
use App\Exceptions\PricingException;
use App\Jobs\SendBookingCodeSms;
use App\Models\Service\Service;
use App\Services\BookingCodeService;
use App\Services\PricingCalculator;
use App\Services\SlotAvailabilityReader;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RussianDate;
use App\ValueObjects\VehicleParams;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Шаг 3 «Данные» (мокап booking/details.html): контакты клиента → запрос SMS-кода (ФТ-7).
 *
 * Персональные данные в URL не пишутся (ADR 0006): контакты живут в сессионном
 * черновике booking_draft; выбор (время/услуги) — сквозной query, он же уходит
 * на шаг «Код». Запись и клиент (Customer) создаются на шаге 4 — здесь только код.
 */
#[Layout('layouts.public')]
class DetailsStepPage extends Component
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):00$/';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    /** @var list<int> */
    #[Url(as: 'services')]
    public array $serviceIds = [];

    /** @var array<int, int> */
    #[Url(as: 'quantities')]
    public array $quantities = [];

    #[Url]
    public ?int $radius = null;

    #[Url(as: 'car_type')]
    public ?string $carType = null;

    public string $name = '';

    public string $phone = '';

    public ?string $plate = null;

    public function mount(SlotAvailabilityReader $reader): void
    {
        // Шаг осмыслен только с выбираемым временем: мусор/прошлое/закрытый слот — перевыбрать на шаге 1
        $date = $this->parseDate($this->date);
        $hour = $this->parseTimeHour($this->time);
        if ($date === null
            || $hour === null
            || ! $reader->isWithinBookingWindow($date)
            || ! $reader->isSelectableHour($date, $hour)) {
            $this->redirect(route('booking.time'));

            return;
        }

        $this->serviceIds = $this->filterActiveServiceIds($this->serviceIds);
        $this->quantities = $this->normalizeQuantities($this->quantities);
        $this->radius = $this->radius !== null ? WheelRadiusEnum::tryFrom($this->radius)?->value : null;
        $this->carType = $this->carTypeValueOrNull($this->carType);

        // Возврат с шага «Код»: предзаполняем форму из черновика
        $draft = session('booking_draft');
        if (is_array($draft)) {
            $this->name = (string) ($draft['name'] ?? '');
            $this->phone = (string) ($draft['phone'] ?? '');
            $this->plate = $draft['plate'] ?? null;
        }
    }

    public function submit(BookingCodeService $codes, SlotAvailabilityReader $reader): void
    {
        $name = trim($this->name);
        $phone = Phone::normalize($this->phone);
        $plate = $this->plate !== null ? trim($this->plate) : null;

        if ($name === '') {
            $this->addError('name', 'Укажите имя');
        }
        if ($phone === null) {
            $this->addError('phone', 'Укажите корректный телефон');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Время могло стать недоступным с момента открытия шага — код не отправляем (ФТ-7)
        $date = $this->parseDate($this->date);
        $hour = $this->parseTimeHour($this->time);
        if ($date === null
            || $hour === null
            || ! $reader->isWithinBookingWindow($date)
            || ! $reader->isSelectableHour($date, $hour)) {
            $this->addError('slot', 'Время недоступно — выберите другое время');

            return;
        }

        session(['booking_draft' => [
            'name' => $name,
            'phone' => $phone,
            'plate' => $plate === '' ? null : $plate,
        ]]);

        $code = $codes->issue($phone);
        SendBookingCodeSms::dispatch($phone, $code);

        $this->redirect(route('booking.code', [
            'date' => $this->date,
            'time' => $this->time,
            'services' => $this->serviceIds,
            'quantities' => $this->quantities,
            'radius' => $this->radius,
            'car_type' => $this->carType,
        ]));
    }

    public function render(PricingCalculator $calculator): View
    {
        $date = CarbonImmutable::parse($this->date);

        return view('livewire.booking.details-step-page', [
            'datetimeLabel' => RussianDate::dayShort($date).', '.$this->time,
            'summaryDateLabel' => RussianDate::dayShort($date),
            'quote' => $this->summaryQuote($calculator),
            'servicesUrl' => route('booking.services', [
                'date' => $this->date,
                'time' => $this->time,
                'services' => $this->serviceIds,
                'quantities' => $this->quantities,
                'radius' => $this->radius,
                'car_type' => $this->carType,
            ]),
            'timeUrl' => route('booking.time', ['date' => $this->date, 'time' => $this->time]),
        ]);
    }

    /**
     * Итог выбранного набора для сайдбара; неполный набор и потерянное правило
     * цен не показывают (страница шага 3 не сообщает об ошибке прайса — она уйдёт
     * на подтверждение).
     *
     * @return null|array{lines: list<array{name: string, quantity: int, price: string}>, total: string}
     */
    private function summaryQuote(PricingCalculator $calculator): ?array
    {
        $carType = CarTypeEnum::tryFrom((string) $this->carType);
        if ($this->serviceIds === [] || $this->radius === null || $carType === null) {
            return null;
        }

        $services = Service::query()
            ->whereIn('id', $this->serviceIds)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        $quantities = [];
        foreach ($services as $service) {
            $quantities[$service->id] = $this->quantities[$service->id] ?? 4;
        }

        try {
            $quote = $calculator->quote($services, new VehicleParams($this->radius, $carType), $quantities);
        } catch (PricingException) {
            return null;
        }

        return [
            'lines' => array_map(
                fn (array $line): array => [
                    'name' => $line['service']->name,
                    'quantity' => $line['quantity'],
                    'price' => Money::format($line['price']),
                ],
                $quote['lines'],
            ),
            'total' => Money::format($quote['total']),
        ];
    }

    /** @param  list<int>  $ids */
    private function filterActiveServiceIds(array $ids): array
    {
        return Service::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $quantities
     * @return array<int, int>
     */
    private function normalizeQuantities(array $quantities): array
    {
        $normalized = [];
        foreach ($this->serviceIds as $serviceId) {
            $raw = $quantities[$serviceId] ?? 4;
            $normalized[$serviceId] = max(1, min(4, (int) $raw));
        }

        return $normalized;
    }

    private function carTypeValueOrNull(?string $value): ?string
    {
        $carType = CarTypeEnum::tryFrom((string) $value);

        return $carType !== null && in_array($carType, CarTypeEnum::bookable(), true) ? $carType->value : null;
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

    private function parseTimeHour(?string $value): ?int
    {
        if ($value === null || preg_match(self::TIME_PATTERN, $value) !== 1) {
            return null;
        }

        return (int) substr($value, 0, 2);
    }
}
