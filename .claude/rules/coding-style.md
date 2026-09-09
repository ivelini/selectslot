# Стиль кода TireSlot (проектные дополнения к глобальным принципам)

Общие принципы и глобальный чек-лист «Перед коммитом» — `~/.claude/rules/coding-style.md`. Здесь — слои и конвенции этого проекта (карта: CLAUDE.md, решения: ADR 0010).

## Словарь слоёв (ADR 0010)

| Каталог | Что кладём | Пример |
|---|---|---|
| `app/Actions/` | однооперационные действия: имя `{Глагол}{Существительное}Action`, единственный публичный метод `handle()` | `CreateBookingAction`, `CalculatePriceAction`, `GenerateSlotGridAction` |
| `app/Services/` | службы областей — несколько связанных операций с общим приватным контекстом; адаптеры внешнего мира (реализации `Contracts\`) | `BookingCodeService`, `SlotAvailabilityReader`, `LogSmsSender` |
| `app/Support/` | чистые функции (без БД, без Livewire) | `Phone`, `Money`, `RussianDate`, `BookingQuery` |
| `app/ValueObjects/` | данные без логики | `VehicleParams`, `BookingSelection` |
| модели | тонкие: связи, скоупы-фильтры, хелперы-чтения | `Service::scopeActiveByIds`, `Booking::forCode` |

## Параметры конфигурации

- Бизнес-параметр (таблица 7 ФТ: срок кода, мин. время, горизонт, порог отмены) читай только через `Setting::get(SettingKeyEnum::…)`. Строку-ключ или дефолт в классе не объявляй — добавь кейс в `app/Enums/Settings/SettingKeyEnum.php`.
- Техническое значение (не параметр домена, из админки не правится: кулдаун SMS) — в `config/services.php` (`services.sms.resend_cooldown_seconds`), не константой класса и не в `settings`.

## Публичный сайт (Livewire `app/Livewire/Booking/`)

- Шаг с выбором (2–4) наследуй `SelectionStepPage`: свои регэкспы форматов, парсеры и нормализацию в компоненте не пиши — вход из query обрабатывай через `Support\BookingQuery` и каркас (`afterMount()` — специфика шага, `selectableDateTime()` — guard на submit-фазе).
- Guard времени в `confirmBooking` шага «Код» — только формат (`selectionDateTime`), не доступность слота: закрытый слот ловит `CreateBookingAction` → редирект unavailable (поведение зафиксировано тестом `test_slot_closed_redirects_to_unavailable`).

## Перед коммитом — проектные проверки (после глобального чек-листа)

1. Ключи/дефолты параметров — только в `SettingKeyEnum`, чтение — `Setting::get`?
2. В Livewire-компонентах шагов нет своих регэкспов/нормализации входа?
3. Новые классы легли в правильный слой (таблица выше)?
