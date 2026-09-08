<?php

namespace App\Models;

use App\Enums\ServiceCategoryEnum;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ServiceCategoryEnum $category
 * @property bool $is_active
 * @property int $base_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'category', 'is_active', 'base_price'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @return HasMany<PriceRule, $this> */
    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    /** @return HasMany<BookingService, $this> */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    protected function casts(): array
    {
        return [
            'category' => ServiceCategoryEnum::class,
            'is_active' => 'boolean',
        ];
    }
}
