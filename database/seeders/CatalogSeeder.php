<?php

namespace Database\Seeders;

use App\Enums\CarTypeEnum;
use App\Enums\ServiceCategoryEnum;
use App\Models\Service\ComplexService;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Реальный прайс-лист (2026-09): цены за 1 колесо в рублях. Работы таблиц прайса —
     * цены по группам радиусов (R12–15 / R16–17 / R18–19 / R20–21) и двум колонкам типов
     * («легковые» — passenger; «внедорожники» — crossover/suv с общей ценой).
     * base_price услуги = минимальная цена (легковые, R12–15) — «от N ₽» на карточке.
     */
    private const PRICE_LIST = [
        'Снятие и установка колёс' => [
            'passenger' => [15000, 22000, 30000, 38000],
            'suv' => [22000, 30000, 38000, 42000],
        ],
        'Демонтаж колёс' => [
            'passenger' => [12000, 16000, 19000, 22000],
            'suv' => [16000, 20000, 22000, 25000],
        ],
        'Монтаж колёс' => [
            'passenger' => [12000, 16000, 19000, 21000],
            'suv' => [16000, 20000, 21000, 25000],
        ],
        'Балансировка колёс' => [
            'passenger' => [14000, 21000, 26000, 38000],
            'suv' => [21000, 30000, 38000, 40000],
        ],
        'Комплекс работ (1 колесо)' => [
            'passenger' => [53000, 75000, 94000, 119000],
            'suv' => [75000, 100000, 119000, 132000],
        ],
    ];

    /** Радиусы сайта (R13–R21) по группам прайса. */
    private const RADIUS_GROUPS = [
        [13, 14, 15],
        [16, 17],
        [18, 19],
        [20, 21],
    ];

    /**
     * Дополнительные работы прайса (не входят в комплекс): поштучные, правил нет —
     * цена по base_price за штуку (количество задаёт клиент).
     */
    private const EXTRA_WORKS = [
        ['Замена вентиля', 5000],
        ['Установка датчика давления (TPMS)', 10000],
        ['Монтаж/демонтаж шин RunFlat', 10000],
        ['Покрытие ступицы антикоррозийной смазкой', 10000],
        ['Утилизация шины', 20000],
    ];

    public function run(): void
    {
        $this->seedPriceListWorks();
        $this->seedExtraWorks();
        $this->seedComplexes();
        $this->deactivateRemovedServices();
    }

    /** Готовые комплексы: «Сезонный шиномонтаж» = 4 работы переобувки (клик отмечает их ×4). */
    private function seedComplexes(): void
    {
        $complex = ComplexService::updateOrCreate(['name' => 'Сезонный шиномонтаж'], ['is_active' => true]);

        $serviceIds = Service::whereIn('name', [
            'Снятие и установка колёс',
            'Демонтаж колёс',
            'Монтаж колёс',
            'Балансировка колёс',
        ])->pluck('id');

        $complex->services()->sync($serviceIds);
    }

    /** Работы прайса: полный куб правил (услуга × радиус × тип) — подбор точным совпадением (ADR 0007). */
    private function seedPriceListWorks(): void
    {
        foreach (self::PRICE_LIST as $name => $columns) {
            $service = Service::updateOrCreate(
                ['name' => $name],
                ['category' => ServiceCategoryEnum::Tire, 'is_active' => true, 'base_price' => $columns['passenger'][0]],
            );

            foreach (self::RADIUS_GROUPS as $groupIndex => $radii) {
                foreach ($radii as $radius) {
                    foreach (CarTypeEnum::bookable() as $carType) {
                        $column = $carType === CarTypeEnum::Passenger ? 'passenger' : 'suv';

                        PriceRule::updateOrCreate(
                            [
                                'service_id' => $service->id,
                                'radius' => $radius,
                                'car_type' => $carType,
                            ],
                            ['price' => $columns[$column][$groupIndex]],
                        );
                    }
                }
            }
        }
    }

    private function seedExtraWorks(): void
    {
        foreach (self::EXTRA_WORKS as [$name, $price]) {
            Service::updateOrCreate(
                ['name' => $name],
                ['category' => ServiceCategoryEnum::Tire, 'is_active' => true, 'base_price' => $price],
            );
        }
    }

    /** Услуги вне прайса (устаревший каталог) деактивируются, их правила удаляются. */
    private function deactivateRemovedServices(): void
    {
        $keep = [...array_keys(self::PRICE_LIST), ...array_column(self::EXTRA_WORKS, 0)];
        $removedIds = Service::whereNotIn('name', $keep)->pluck('id');

        if ($removedIds->isNotEmpty()) {
            PriceRule::whereIn('service_id', $removedIds)->delete();
            Service::whereIn('id', $removedIds)->update(['is_active' => false]);
        }
    }
}
