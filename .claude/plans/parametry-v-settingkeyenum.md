# Параметры конфигурации: SettingKeyEnum + Setting::get, кулдаун в config

**Цель:** убрать размазанные по классам ключи/дефолты/хелперы чтения таблицы settings (паттерн повторён 3 раза, ключ `booking_horizon_days` задублирован); публичная константа кулдауна переезжает в config. `BookingCodeService` остаётся службой области (ADR 0010) — разбиение и наследование отклонены в обсуждении.

**Задачи:**
1. `app/Enums/Settings/SettingKeyEnum` — ключи таблицы settings (таблица 7 ФТ): ReservationTimeoutMin (15), MinLeadTimeH (1), HorizonDays (30) + `default()`
2. `Setting::get(SettingKeyEnum $key): int` — актуальное значение или дефолт
3. Классы: `BookingCodeService` (verify), `SlotAvailabilityReader` (3 места), `GenerateSlotGridAction` — константы и приватные хелперы уходят, чтение через `Setting::get`
4. Кулдаун `RESEND_COOLDOWN_SECONDS` (не параметр таблицы 7 ФТ — техническая защита, ФТ-23) → `config('services.sms.resend_cooldown_seconds')`; `CodeStepPage` читает из config

**Ожидаемый результат:** единственный источник ключей/дефолтов — enum; службы и действия не знают строк-ключей.

**Тест-лист (новое поведение — только `Setting::get`):**
- `Setting::get` возвращает сохранённое значение: строка `booking_horizon_days` = '7' → 7 (test_get_returns_stored_value)
- `Setting::get` при отсутствующей строке возвращает дефолт enum'а: пустая таблица, HorizonDays → 30 (test_get_returns_default_when_row_missing)
- Дефолты enum соответствуют таблице 7 ФТ: 15 мин / 1 ч / 30 дней (test_defaults_match_ft_parameters_table)
- Регрессия: 76 тестов зелёные (поведение служб/действий не меняется — те же ключи и значения)

**Прогоны:**
- Красный: не применим — значения параметров не меняются; контракт — существующий набор + новые тесты читателя
- Зелёный: `76 passed (249 assertions)` (3 новых теста SettingTest)
