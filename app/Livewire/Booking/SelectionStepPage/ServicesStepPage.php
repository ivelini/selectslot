<?php

namespace App\Livewire\Booking\SelectionStepPage;

use App\Actions\CalculatePriceAction;
use App\Enums\CarTypeEnum;
use App\Enums\WheelRadiusEnum;
use App\Exceptions\PricingException;
use App\Models\Service\ComplexService;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Support\BookingQuery;
use App\Support\Money;
use App\Support\RussianDate;
use App\ValueObjects\VehicleParams;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;

/**
 * Шаг 2 «Услуги» (мокап booking/services.html): услуги с количеством + параметры авто, живой расчёт.
 *
 * Прайс — за единицу (1 колесо/шт): у выбранной услуги счётчик 1–4 (дефолт 4).
 * Выбор зеркалится в query (ADR 0006): ?services[]=&quantities[id]=&radius=&car_type=.
 * Радиус и тип обязательны к переходу («не знаю» нет); «Грузовик» сайт не предлагает (ФТ-18);
 * RunFlat/TPMS — доп. работы, а не параметры: на цену правила не влияют.
 */
#[Layout('layouts.public')]
class ServicesStepPage extends SelectionStepPage
{
    public function toggleService(int $serviceId): void
    {
        if (! Service::query()->whereKey($serviceId)->where('is_active', true)->exists()) {
            return;
        }

        if (in_array($serviceId, $this->serviceIds, true)) {
            $this->serviceIds = array_values(array_diff($this->serviceIds, [$serviceId]));
            unset($this->quantities[$serviceId]);
        } else {
            $this->serviceIds[] = $serviceId;
            $this->quantities[$serviceId] = BookingQuery::DEFAULT_QUANTITY;
        }
    }

    /**
     * Клик по комплексу: состав полон и все его услуги = 4 → снять услуги комплекса;
     * иначе — привести к комплекту ×4: недостающие добавить, ВСЕ услуги комплекса = 4
     * (уже выбранные с другим количеством переустанавливаются).
     */
    public function toggleComplex(int $complexId): void
    {
        $serviceIds = ComplexService::query()
            ->whereKey($complexId)
            ->where('is_active', true)
            ->first()
            ?->services()
            ->where('services.is_active', true)
            ->orderBy('services.id')
            ->pluck('services.id')
            ->all() ?? [];

        if ($serviceIds === []) {
            return;
        }

        $allPresent = array_diff($serviceIds, $this->serviceIds) === [];
        $allFour = $allPresent && collect($serviceIds)->every(
            fn (int $id): bool => ($this->quantities[$id] ?? 0) === BookingQuery::DEFAULT_QUANTITY,
        );

        if ($allFour) {
            $this->serviceIds = array_values(array_diff($this->serviceIds, $serviceIds));
            foreach ($serviceIds as $serviceId) {
                unset($this->quantities[$serviceId]);
            }

            return;
        }

        $this->serviceIds = array_values(array_unique([...$this->serviceIds, ...$serviceIds]));
        foreach ($serviceIds as $serviceId) {
            $this->quantities[$serviceId] = BookingQuery::DEFAULT_QUANTITY;
        }
    }

    public function incrementQuantity(int $serviceId): void
    {
        if (! in_array($serviceId, $this->serviceIds, true)) {
            return;
        }

        $this->quantities[$serviceId] = min(BookingQuery::MAX_QUANTITY, $this->quantities[$serviceId] + 1);
    }

    public function decrementQuantity(int $serviceId): void
    {
        if (! in_array($serviceId, $this->serviceIds, true)) {
            return;
        }

        $this->quantities[$serviceId] = max(BookingQuery::MIN_QUANTITY, $this->quantities[$serviceId] - 1);
    }

    public function selectRadius(int $radius): void
    {
        if (WheelRadiusEnum::tryFrom($radius) === null) {
            return;
        }

        $this->radius = $radius;
    }

    public function selectCarType(string $carType): void
    {
        $this->carType = BookingQuery::carTypeValueOrNull($carType);
    }

    public function render(CalculatePriceAction $calculatePrice): View
    {
        $catalog = Service::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'base_price']);
        $selected = $catalog->whereIn('id', $this->serviceIds)->values();
        $carType = CarTypeEnum::tryFrom((string) $this->carType);
        $params = $this->radius !== null && $carType !== null
            ? new VehicleParams($this->radius, $carType)
            : null;

        // Один расчёт каталога (цена за единицу при выбранных параметрах) кормит и карточки
        // услуг, и сайдбар; потерянное правило — PricingException — не роняет страницу.
        $unitPrices = [];
        $pricingError = false;
        if ($params !== null) {
            try {
                $quote = $calculatePrice->handle($catalog, $params, $this->unitQuantities($catalog));
                foreach ($quote['lines'] as $line) {
                    $unitPrices[$line['service']->id] = $line['unit_price'];
                }
            } catch (PricingException) {
                $pricingError = true;
            }
        }

        $quote = $params !== null && ! $pricingError && $selected->isNotEmpty()
            ? $this->summaryFromUnitPrices($selected, $unitPrices)
            : null;

        return view('livewire.booking.services-step-page', [
            'catalog' => $catalog,
            'selectedIds' => $this->serviceIds,
            'quantities' => $this->quantities,
            'complexes' => $this->complexesData(),
            'unitPrices' => $unitPrices,
            'ruleServiceIds' => $this->ruleServiceIds($catalog),
            'radiusOptions' => WheelRadiusEnum::cases(),
            'carTypeOptions' => CarTypeEnum::bookable(),
            'quote' => $quote,
            'pricingError' => $pricingError,
            'summaryDateLabel' => RussianDate::dayShort(CarbonImmutable::parse($this->date)),
            'continueUrl' => $quote !== null ? $this->continueUrl() : null,
        ]);
    }

    /**
     * Активные комплексы с состоянием относительно выбора (в URL не пишутся — состояние
     * выводится из выбранных услуг). none/partial/full — по вхождению услуг комплекса.
     *
     * @return list<array{id: int, name: string, state: string, service_names: list<string>}>
     */
    private function complexesData(): array
    {
        $complexes = ComplexService::query()
            ->where('is_active', true)
            ->with(['services' => fn ($query) => $query->where('services.is_active', true)->orderBy('services.id')])
            ->orderBy('id')
            ->get();

        return $complexes->map(function (ComplexService $complex): array {
            $serviceIds = $complex->services->pluck('id')->all();
            $present = array_values(array_intersect($serviceIds, $this->serviceIds));

            $state = match (count($present)) {
                0 => 'none',
                count($serviceIds) => 'full',
                default => 'partial',
            };

            return [
                'id' => $complex->id,
                'name' => $complex->name,
                'state' => $state,
                'service_names' => $complex->services->pluck('name')->all(),
            ];
        })->all();
    }

    /**
     * Расчёт каталога с количеством 1 на услугу — цены за единицу для карточек.
     *
     * @return array<int, int> service_id => 1
     */
    private function unitQuantities(Collection $catalog): array
    {
        return $catalog->pluck('id')->mapWithKeys(fn (int $id): array => [$id => 1])->all();
    }

    /**
     * Строки сайдбара из цен за единицу: итог строки = цена × количество.
     *
     * @return array{lines: list<array{service_id: int, name: string, quantity: int, price: string}>, total: string}
     */
    private function summaryFromUnitPrices(Collection $selected, array $unitPrices): array
    {
        $lines = [];
        $total = 0;
        foreach ($selected as $service) {
            $quantity = $this->quantities[$service->id] ?? BookingQuery::DEFAULT_QUANTITY;
            $linePrice = $unitPrices[$service->id] * $quantity;

            $lines[] = [
                'service_id' => $service->id,
                'name' => $service->name,
                'quantity' => $quantity,
                'price' => Money::format($linePrice),
            ];
            $total += $linePrice;
        }

        return ['lines' => $lines, 'total' => Money::format($total)];
    }

    /** @return list<int> id услуг каталога, у которых есть прайс-правила (цена зависит от параметров) */
    private function ruleServiceIds(Collection $catalog): array
    {
        return PriceRule::query()
            ->whereIn('service_id', $catalog->pluck('id'))
            ->distinct()
            ->pluck('service_id')
            ->all();
    }

    private function continueUrl(): string
    {
        return route('booking.details', $this->selectionQueryParams());
    }
}
