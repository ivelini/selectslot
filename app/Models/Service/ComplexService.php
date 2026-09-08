<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Готовый комплекс услуг (например, «Сезонный шиномонтаж»): набор без собственной цены.
 * На сайте клик по комплексу отмечает входящие услуги с количеством 4; в запись комплекс
 * не попадает — хранятся только услуги с количествами.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_active'])]
class ComplexService extends Model
{
    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'complex_service_item');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
