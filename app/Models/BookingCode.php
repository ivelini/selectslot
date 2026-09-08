<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Заявка виджета до подтверждения: данные заявки (payload) живут вместе с кодом (ФТ-7, ADR 0002).
 *
 * @property int $id
 * @property string $phone
 * @property string $code_hash
 * @property array $payload
 * @property Carbon $expires_at
 * @property Carbon|null $used_at код создаёт ровно одну запись
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['phone', 'code_hash', 'payload', 'expires_at', 'used_at'])]
class BookingCode extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
