<?php

namespace App\Enums\Settings;

/**
 * Ключи таблицы settings (параметры конфигурации, НФ-3, таблица 7 ФТ):
 * значения по умолчанию — здесь, актуальное значение — Setting::get().
 */
enum SettingKeyEnum: string
{
    /** ФТ-9: срок действия кода подтверждения (неподтверждённый), минуты */
    case ReservationTimeoutMin = 'reservation_timeout_min';

    /** ФТ-10: минимальное время записи до начала, часы */
    case MinLeadTimeH = 'min_lead_time_h';

    /** ФТ-10: горизонт записи (сколько дней вперёд открыта сетка), дни */
    case HorizonDays = 'booking_horizon_days';

    public function default(): int
    {
        return match ($this) {
            self::ReservationTimeoutMin => 15,
            self::MinLeadTimeH => 1,
            self::HorizonDays => 30,
        };
    }
}
