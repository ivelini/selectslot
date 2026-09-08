<?php

namespace Database\Seeders;

use App\Enums\CarTypeEnum;
use App\Enums\ServiceCategoryEnum;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /** Цены демо-сценария (мокап .template/): Снятие/установка 600 ₽, Балансировка 400 ₽ и т.д. */
    private const SERVICES = [
        ['Снятие/установка колёс', ServiceCategoryEnum::Tire, 60000],
        ['Балансировка колёс', ServiceCategoryEnum::Tire, 40000],
        ['Ремонт прокола', ServiceCategoryEnum::Tire, 50000],
        ['Вулканизация', ServiceCategoryEnum::Tire, 80000],
        ['Замена ниппеля', ServiceCategoryEnum::Tire, 15000],
        ['Правка дисков', ServiceCategoryEnum::Tire, 70000],
        ['Хранение шин (сезон)', ServiceCategoryEnum::Storage, 250000],
        ['Подготовка к сезону', ServiceCategoryEnum::Other, 90000],
    ];

    public function run(): void
    {
        foreach (self::SERVICES as [$name, $category, $basePrice]) {
            Service::updateOrCreate(
                ['name' => $name],
                ['category' => $category, 'is_active' => true, 'base_price' => $basePrice],
            );
        }

        $this->seedMountingRules();
    }

    /**
     * Ценовые правила для «Снятие/установка колёс» (ФТ-2): демо-формула из мокапа —
     * базовый тариф R≤15: 600 ₽, далее +250 ₽ за радиус, +150 ₽ кроссовер / +350 ₽ внедорожник, +300 ₽ RunFlat.
     * Правила хранят абсолютную цену комбинации; подбор точным совпадением (будущий PricingService).
     */
    private function seedMountingRules(): void
    {
        $mounting = Service::where('name', 'Снятие/установка колёс')->firstOrFail();

        foreach (range(13, 18) as $radius) {
            foreach (CarTypeEnum::cases() as $carType) {
                if ($carType === CarTypeEnum::Truck) {
                    continue;
                }

                $typeMarkup = match ($carType) {
                    CarTypeEnum::Crossover => 15000,
                    CarTypeEnum::Suv => 35000,
                    default => 0,
                };
                $radiusMarkup = max(0, $radius - 15) * 25000;
                $base = 60000 + $radiusMarkup + $typeMarkup;

                foreach ([false, true] as $hasRunflat) {
                    $runflatMarkup = $hasRunflat ? 30000 : 0;

                    PriceRule::updateOrCreate(
                        [
                            'service_id' => $mounting->id,
                            'radius' => $radius,
                            'car_type' => $carType,
                            'has_runflat' => $hasRunflat,
                            'has_tpms' => false,
                        ],
                        ['price' => $base + $runflatMarkup],
                    );
                }
            }
        }
    }
}
