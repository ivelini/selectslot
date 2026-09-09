<?php

namespace App\Models;

use App\Enums\Settings\SettingKeyEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Параметры конфигурации (key/value), управляемые из админки без деплоя (НФ-3).
 *
 * @property string $key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /** Актуальное значение параметра; строки нет — дефолт из SettingKeyEnum. */
    public static function get(SettingKeyEnum $key): int
    {
        return (int) (static::find($key->value)?->value ?? $key->default());
    }
}
