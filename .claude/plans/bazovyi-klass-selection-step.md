# Каркас шагов выбора: базовый класс SelectionStepPage

**Цель:** убрать дублирование mount-пролога шагов 2–4 (guard «время выбираемо» ×5 мест, нормализация ×3, `#[Url]`-поля ×3, массивы query для route ×4–5). Каркас компонента (поля + последовательность mount) — случай наследования, а не чистых функций (Support\BookingQuery не выражает редиректы/состояние). Livewire-механика проверена: `getPublicPropertiesDefinedOnSubclass` исключает только свойства самого `Livewire\Component` — унаследованные от промежуточного класса поля (и `#[Url]`) становятся состоянием компонента.

**Задачи:**
1. `app/Livewire/Booking/SelectionStepPage` (abstract extends Component): `#[Url]`-поля выбора; `mount`: guard → normalize → `afterMount()`; `selectionDateTime()` (формат), `selectableDateTime(reader)` (формат + окно + слот), `selectionQueryParams()` (массив для route)
2. Шаги 2–4 наследуют: Services — специфики нет (mount исчезает); Details — `afterMount()` prefill из черновика, guard в submit через `selectableDateTime`; Code — `afterMount()` draft-guard, `confirmBooking` через `selectionDateTime` (закрытый слот НЕ pre-проверяется — его ловит `CreateBookingAction` → redirect unavailable, поведение сохранено), URL через `selectionQueryParams()`
3. Шаг 1 (TimeStep) не включён: другой характер пролога (сам выбирает день/месяц)

**Ожидаемый результат:** поведение не меняется; шаги 2–4 не содержат пролога и полей выбора (остаётся специфика + публичные действия).

**Тест-лист (регрессия — поведение не меняется):**
- Code: закрытый слот при подтверждении → redirect unavailable (НЕ addError — pre-проверка доступности в confirmBooking запрещена)
- Code: без черновика → redirect details; Code: «выбор устарел» — только при невалидном формате/неполном выборе
- Details: время недоступно на submit → addError, код не отправляется
- Шаги: мусор/прошлое/закрытый слот в query → redirect на шаг 1; нормализация выбора на входе
- Livewire-состояние: `#[Url]`-поля из предка синхронизируются (тесты шагов с query)

**Прогоны:**
- Красный: `CodeStepPageTest::test_slot_closed_redirects_to_unavailable` — поймал изменение поведения (confirmBooking переведён на `selectableDateTime` с pre-проверкой слота); исправлено на `selectionDateTime` (формат без доступности)
- Зелёный: `85 passed (269 assertions)`
