# Перенос: каталоги доменного слоя (Actions/Services) + конвенция имён

**Цель:** доменный слой с единой конвенцией: однооперационные действия `{Глагол}{Существительное}Action` с `handle()` — `app/Actions/`; службы областей и адаптеры — `app/Services/`. Решение повышено в ADR 0010.

**Задачи:**
1. Развести каталоги (первая итерация): команды в `app/Actions/`, CQS-чистка `Booking::forCode()` из `BookingCreator`
2. Конвенция `…Action + handle()`: `BookingCreator`→`CreateBookingAction`, `PricingCalculator`→`CalculatePriceAction` (переезд из Services в Actions), `SlotGridGenerator`→`GenerateSlotGridAction`
3. Потребители: `CodeStepPage`, `DetailsStepPage`, `ServicesStepPage`, `SlotsGenerate`, `SlotSeeder`, `DemoBookingSeeder`
4. Тесты: `tests/Unit/Actions/` — `CreateBookingActionTest`, `CalculatePriceActionTest`, `GenerateSlotGridActionTest`; `BookingTest` (границы `Booking::forCode`); фикс фабрики `Booking` (модель в подпапке: `newFactory()` + `#[UseModel]`)
5. ADR 0010 (критерий — гранулярность, не CQS) + индексы (README.md, CLAUDE.md)

**Ожидаемый результат:** поведение не меняется; Actions = одно действие с `handle()`, Services = службы областей (`BookingCodeService`, `SlotAvailabilityReader`) + адаптер (`LogSmsSender`).

**Тест-лист (регрессия — перенос без нового поведения):**
- Сквозной сценарий «повторный submit использованного кода» (verify → Used → `Booking::forCode` → та же запись, без дубля) — `CreateBookingActionTest::test_used_code_returns_existing_booking` (сквозной, хелперы Creator-подготовки; прямые тесты метода — в BookingTest)
- `Booking::forCode`: находит запись, созданную кодом; код без записи → null
- Фабрика `Booking` резолвится (`newFactory()` + `#[UseModel]`)
- Полный прогон тестов зелёный (сиды, Livewire-шаги не затронуты)

**Прогоны:**
- Красный: не применим — рефакторинг с сохранением поведения, контракт — существующий зелёный набор (в ходе работ красный ловил пропущенных потребителей: `SlotSeeder`, `DemoBookingSeeder`, `->generate()` в тестах)
- Зелёный: `73 passed (244 assertions)` после каждой итерации (каталоги; конвенция имён)
