# Общий код шагов записи: Support\BookingQuery + скоуп Service

**Цель:** убрать дубли контракта query-параметров шагов (ADR 0006): константы форматов (4 копии), парсеры и нормализаторы (3–4 копии), границы количества 1–4 и литералы `?? 4`. Rule of Three пройден; формы выноса — Support-класс (стиль Phone/Money), не трейт/базовый класс (согласовано).

**Задачи:**
1. `app/Support/BookingQuery` — чистые функции: `parseDate`, `parseTimeHour` (час:00 — сайт даёт целые часы), `normalizeQuantities` (дефолт 4 — сезонный комплект, кламп 1–4), `carTypeValueOrNull` (не bookable → null); публичные константы DEFAULT/MIN/MAX_QUANTITY
2. Скоуп `Service::scopeActiveByIds` — нормализация устаревшего выбора (БД-метод, Support не место)
3. 4 страницы (Time/Code/Details/Services): константы и приватные хелперы удалены, вызовы — `BookingQuery::…`; счётчики шага «Услуги» — на константах границ; Details/Code литералы `?? 4` → `DEFAULT_QUANTITY`
4. Тесты: `tests/Unit/Support/BookingQueryTest` (чистые функции, без Livewire/БД)
5. Побочно: тесты Actions приведены к новым неймспейсам enum'ов `App\Enums\Booking\` (параллельный перенос пользователя)

**Ожидаемый результат:** страницы шагов не содержат регэкспов форматов и правил количества; поведение не меняется (значения те же).

**Тест-лист (новое поведение — только BookingQuery; регрессия — существующий набор):**
- `parseDate`: '2026-09-10' → дата; мусор/`10.09.2026`/`2026-9-10`/переполнение `2026-99-99` (round-trip)/null → null (test_parse_date_*)
- `parseTimeHour`: '11:00' → 11; границы 00:00/23:00; '12:30' (минуты не время шага), '25:00', null → null (test_parse_time_hour_*)
- `normalizeQuantities`: отсутствующее → 4; кламп 0→1 и 9→4; лишние service_id отбрасываются (test_normalize_quantities_*)
- `carTypeValueOrNull`: bookable → значение; грузовик (ФТ-4, по звонку), мусор, null → null (test_car_type_value_*)
- Регрессия: Feature-тесты шагов (время/услуги/данные/код, result pages) зелёные — значения форматов не менялись

**Прогоны:**
- Красный: не применим — перенос без изменения поведения; промежуточные красные ловили параллельный перенос enum'ов (`App\Enums\Booking\`) в тестах Actions
- Зелёный: `85 passed (269 assertions)` (BookingQueryTest: 9 тестов)
