<?php

namespace App\Models\Service;

use App\Enums\CarTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $radius
 * @property CarTypeEnum $car_type
 * @property bool $has_runflat
 * @property bool $has_tpms
 * @property int $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_id', 'radius', 'car_type', 'has_runflat', 'has_tpms', 'price'])]
class PriceRule extends Model
{
    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return [
            'car_type' => CarTypeEnum::class,
            'has_runflat' => 'boolean',
            'has_tpms' => 'boolean',
        ];
    }
}
