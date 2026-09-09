<?php

namespace App\Actions;

use App\Exceptions\PricingException;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\ValueObjects\VehicleParams;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Расчёт цены набора услуг по прайс-правилам (ФТ-2, ФТ-5).
 *
 * Единый источник для всех каналов (НФ-4): показ итога на сайте и серверный
 * пересчёт при подтверждении кода используют один и тот же класс.
 *
 * Прайс-правило — цена за единицу (1 колесо/шт) для комбинации (услуга, радиус, тип);
 * строка итога = цена × количество. Подбор — только точным совпадением:
 * - у услуги нет правил вовсе — цена не зависит от параметров → base_price;
 * - правила есть, комбинации нет — PricingException (потерянное правило —
 *   баг данных; молчаливая база дала бы неверную цену).
 */
class CalculatePriceAction
{
    /**
     * @param  Collection<int, Service>  $services
     * @param  array<int, int>  $quantities  service_id => количество 1–4
     * @return array{lines: list<array{service: Service, unit_price: int, quantity: int, price: int}>, total: int} цены в копейках
     */
    public function handle(Collection $services, VehicleParams $params, array $quantities): array
    {
        $rulesByService = $this->rulesByService($services);

        $lines = [];
        $total = 0;
        foreach ($services as $service) {
            $quantity = $this->quantityFor($service->id, $quantities);
            $unitPrice = $this->priceFor($service, $rulesByService[$service->id] ?? [], $params);
            $linePrice = $unitPrice * $quantity;

            $lines[] = [
                'service' => $service,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'price' => $linePrice,
            ];
            $total += $linePrice;
        }

        return ['lines' => $lines, 'total' => $total];
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function quantityFor(int $serviceId, array $quantities): int
    {
        $quantity = $quantities[$serviceId] ?? 1;
        if ($quantity < 1 || $quantity > 4) {
            throw new InvalidArgumentException("Количество услуги {$serviceId} вне границ 1–4");
        }

        return $quantity;
    }

    /**
     * @return array<int, list<PriceRule>> service_id => правила
     */
    private function rulesByService(Collection $services): array
    {
        return PriceRule::query()
            ->whereIn('service_id', $services->pluck('id'))
            ->get()
            ->groupBy('service_id')
            ->map(fn ($rules) => $rules->all())
            ->all();
    }

    /**
     * @param  list<PriceRule>  $rules
     */
    private function priceFor(Service $service, array $rules, VehicleParams $params): int
    {
        if ($rules === []) {
            return $service->base_price;
        }

        foreach ($rules as $rule) {
            if ($rule->radius === $params->radius && $rule->car_type === $params->carType) {
                return $rule->price;
            }
        }

        throw new PricingException(sprintf(
            'Нет прайс-правила: услуга %s, R%d, %s',
            $service->name,
            $params->radius,
            $params->carType->label(),
        ));
    }
}
