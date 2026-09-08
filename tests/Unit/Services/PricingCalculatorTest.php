<?php

namespace Tests\Unit\Services;

use App\Enums\CarTypeEnum;
use App\Exceptions\PricingException;
use App\Models\Service\PriceRule;
use App\Models\Service\Service;
use App\Services\PricingCalculator;
use App\ValueObjects\VehicleParams;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): PricingCalculator
    {
        return app(PricingCalculator::class);
    }

    private function service(string $name, int $basePrice): Service
    {
        return Service::create(['name' => $name, 'category' => 'tire', 'is_active' => true, 'base_price' => $basePrice]);
    }

    private function rule(Service $service, int $radius, CarTypeEnum $carType, int $price): void
    {
        PriceRule::create([
            'service_id' => $service->id,
            'radius' => $radius,
            'car_type' => $carType,
            'price' => $price,
        ]);
    }

    public function test_unit_price_multiplied_by_quantity(): void
    {
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        $quote = $this->calculator()->quote(
            collect([$mounting]),
            new VehicleParams(13, CarTypeEnum::Passenger),
            [$mounting->id => 4],
        );

        $line = $quote['lines'][0];
        $this->assertSame(15000, $line['unit_price']);
        $this->assertSame(4, $line['quantity']);
        $this->assertSame(60000, $line['price']);
        $this->assertSame(60000, $quote['total']);
    }

    public function test_rule_by_radius_and_type(): void
    {
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $this->rule($mounting, 13, CarTypeEnum::Crossover, 22000);

        $quote = $this->calculator()->quote(
            collect([$mounting]),
            new VehicleParams(13, CarTypeEnum::Crossover),
            [$mounting->id => 1],
        );

        $this->assertSame(22000, $quote['lines'][0]['price']);
    }

    public function test_service_without_rules_uses_base_price(): void
    {
        $valve = $this->service('Замена вентиля', 5000);

        $quote = $this->calculator()->quote(
            collect([$valve]),
            new VehicleParams(16, CarTypeEnum::Passenger),
            [$valve->id => 2],
        );

        $line = $quote['lines'][0];
        $this->assertSame(5000, $line['unit_price']);
        $this->assertSame(2, $line['quantity']);
        $this->assertSame(10000, $line['price']);
        $this->assertSame(10000, $quote['total']);
    }

    public function test_throw_when_combo_rule_missing(): void
    {
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);

        $this->expectException(PricingException::class);

        $this->calculator()->quote(
            collect([$mounting]),
            new VehicleParams(20, CarTypeEnum::Passenger),
            [$mounting->id => 4],
        );
    }

    public function test_quote_sums_services_with_quantities(): void
    {
        $mounting = $this->service('Снятие и установка колёс', 15000);
        $this->rule($mounting, 13, CarTypeEnum::Passenger, 15000);
        $balancing = $this->service('Балансировка колёс', 14000);
        $this->rule($balancing, 13, CarTypeEnum::Passenger, 14000);

        $quote = $this->calculator()->quote(
            collect([$mounting, $balancing]),
            new VehicleParams(13, CarTypeEnum::Passenger),
            [$mounting->id => 4, $balancing->id => 2],
        );

        $this->assertCount(2, $quote['lines']);
        $this->assertSame([4, 2], array_column($quote['lines'], 'quantity'));
        $this->assertSame([60000, 28000], array_column($quote['lines'], 'price'));
        $this->assertSame(88000, $quote['total']);
    }
}
