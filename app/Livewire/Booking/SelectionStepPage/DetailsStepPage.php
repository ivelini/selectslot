<?php

namespace App\Livewire\Booking\SelectionStepPage;

use App\Actions\CalculatePriceAction;
use App\Enums\CarTypeEnum;
use App\Exceptions\PricingException;
use App\Jobs\SendBookingCodeSms;
use App\Models\Service\Service;
use App\Services\BookingCodeService;
use App\Services\SlotAvailabilityReader;
use App\Support\BookingQuery;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RussianDate;
use App\ValueObjects\VehicleParams;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

/**
 * Шаг 3 «Данные» (мокап booking/details.html): контакты клиента → запрос SMS-кода (ФТ-7).
 *
 * Персональные данные в URL не пишутся (ADR 0006): контакты живут в сессионном
 * черновике booking_draft; выбор (время/услуги) — сквозной query, он же уходит
 * на шаг «Код». Запись и клиент (Customer) создаются на шаге 4 — здесь только код.
 */
#[Layout('layouts.public')]
class DetailsStepPage extends SelectionStepPage
{
    public string $name = '';

    public string $phone = '';

    public ?string $plate = null;

    /** Возврат с шага «Код»: предзаполняем форму из черновика. */
    protected function afterMount(): void
    {
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
        if ($this->selectableDateTime($reader) === null) {
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

        $this->redirect(route('booking.code', $this->selectionQueryParams()));
    }

    public function render(CalculatePriceAction $calculatePrice): View
    {
        $date = CarbonImmutable::parse($this->date);

        return view('livewire.booking.details-step-page', [
            'datetimeLabel' => RussianDate::dayShort($date).', '.$this->time,
            'summaryDateLabel' => RussianDate::dayShort($date),
            'quote' => $this->summaryQuote($calculatePrice),
            'servicesUrl' => route('booking.services', $this->selectionQueryParams()),
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
    private function summaryQuote(CalculatePriceAction $calculatePrice): ?array
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
            $quantities[$service->id] = $this->quantities[$service->id] ?? BookingQuery::DEFAULT_QUANTITY;
        }

        try {
            $quote = $calculatePrice->handle($services, new VehicleParams($this->radius, $carType), $quantities);
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
}
