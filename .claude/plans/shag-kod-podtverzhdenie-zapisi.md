# Шаг «Код» + создание записи; упразднение cars; сквозной пронос шага 1

- **Дата:** 2026-09-09
- **Статус:** согласован, реализация

## Цель

Шаг 4 «Подтверждение» (мокап code/success/unavailable/code-expired): ввод SMS-кода → атомарное создание записи (ФТ-8/9/11) и экраны результата (ADR 0006: редиректы с сессионной пометкой). Сопутствующие решения: сущность `cars` упраздняется (параметры и госномер — снимок записи `bookings.plate`); шаг «Время» сквозно проносит выбор услуг при смене времени.

## Решения (согласованы)

- **A. Упразднить `cars`**: drop таблицы; `bookings.car_id` → `bookings.plate` (nullable); Customer::cars/Car/CarFactory удаляются; демо-сид и BookingFactory — параметры без Car (демо: профиль параметров клиента в памяти сида — эмуляция подстановки «из последней записи»); ФТ-18/22, db-schema — «параметры/госномер из последней записи».
- **B. Сквозной пронос шага 1**: TimeStepPage несёт известные чужие query-параметры (services/quantities/radius/car_type) в ссылку «К выбору услуг» — смена времени не теряет услуги (мокап unavailable).
- **C. Шаг «Код»**: `BookingCodeService::verify(phone, code)` → CodeVerification {Valid/Used/Expired/Invalid, code?} (TTL created_at + reservation_timeout_min). `BookingCreator::confirm` — единый Action (НФ-4): транзакция, `SELECT … FOR UPDATE` слота (закрыт/нет строки → SlotUnavailableException), Customer firstOrCreate, Booking confirmed со снимком (radius/car_type/plate, total — серверный пересчёт), items (price за единицу × quantity), code.used_at атомарно; использованный код → существующая запись по booking_code_id (идемпотентность, НФ-1); черновик очищается после успеха.
- **Экраны результата** (редирект + flash-пометка, ADR 0006): success (детали из БД; «Моя запись»-кнопка опущена — ветки нет), unavailable («выбрать другое время»), code-expired («запросить новый код»); вход без пометки → главная.
- **Кулдаун повторной отправки 60 с** (по последнему коду телефона); Invalid-код — ошибка на форме.
- Код создаётся/отправляется как на шаге 3; без черновика на шаге «Код» → шаг «Данные».

## Задачи

1. A: миграция (drop cars, bookings.plate), модели/фабрики/демо-сид, ФТ v0.15 (ФТ-18/22), db-schema v0.11.
2. B: TimeStepPage — carry-пронос + тесты.
3. C: verify+CodeVerification; BookingCreator + SlotUnavailableException; CodeStepPage + view; экраны (3 компонента+view) + маршруты; очистка draft; кулдаун.
4. Мокап: правки не требуются (эталон полон; Livewire опускает «Мою запись»-кнопку до появления ветки).
5. Прогоны, pint, wiki.

## Тест-лист (согласован)

```
── A. Cars упразднены ────────────────────────────────────────────────────────────

Тест 1
1. Поведение: схемы без cars: записи хранят снимок параметров и госномер в bookings.plate
2. Вход: DatabaseSeeder
3. Ожидание: таблицы cars нет; confirmed-записи имеют radius/car_type; часть — plate
4. Имя: test_seed_bookings_carry_parameters_without_cars (DatabaseSeederTest)

── B. Сквозной пронос шага 1 ─────────────────────────────────────────────────────

Тест 2
1. Поведение: шаг «Время» проносит выбор услуг: ссылка «К выбору услуг» содержит services/quantities/radius/car_type из query
2. Вход: HTTP GET /?date&time&services&quantities&radius&car_type
3. Ожидание: в html href на /booking/services с этими параметрами
4. Имя: test_time_step_carries_selection_through (TimeStepPageTest)

Тест 3
1. Поведение: без чужих параметров ссылка обычная (date/time)
2. Вход: GET /?date&time
3. Ожидание: href без services
4. Имя: (кейс теста 2 — второй запуск)

── C. Код и создание записи ──────────────────────────────────────────────────────

Тест 4  (BookingCodeService)
1. Поведение: verify: активный → Valid; неверный → Invalid; просроченный → Expired; использованный → Used
2. Вход: issue + сдвиг времени; used-код
3. Ожидание: статусы по сценарию
4. Имя: test_verify_returns_valid_used_expired_invalid

Тест 5  (BookingCreator, unit)
1. Поведение: confirm создаёт запись confirmed со снимком и серверным total (цена × qty), items, код отмечен
2. Вход: код Valid + выбор (снятие R13 ×4) + draft
3. Ожидание: Booking.confirmed; строка 60000; total 60000; code.used_at
4. Имя: test_confirm_creates_booking_with_snapshot

Тест 6
1. Поведение: слот закрыт → SlotUnavailableException; ничего не создано, код не использован
2. Вход: слот is_closed=true
3. Ожидание: исключение; bookings 0; used_at null
4. Имя: test_confirm_fails_when_slot_closed

Тест 7
1. Поведение: повторный confirm тем же кодом не дублирует запись (НФ-1): Used → существующая запись
2. Вход: confirm успешен → verify повторно (Used) → bookingForCode
3. Ожидание: bookings 1
4. Имя: test_used_code_returns_existing_booking

Тест 8  (CodeStepPage)
1. Поведение: без черновика в сессии → redirect на шаг «Данные»
2. Вход: чистый визит /booking/code
3. Ожидание: redirect booking.details
4. Имя: test_redirects_to_details_without_draft

Тест 9
1. Поведение: неверный код → ошибка, запись не создана
2. Вход: digits 0000
3. Ожидание: сообщение/ошибка; bookings 0
4. Имя: test_wrong_code_shows_error

Тест 10
1. Поведение: валидный код → запись создана, redirect success, черновик очищен
2. Вход: валидный код (issue), полный выбор
3. Ожидание: bookings 1; redirect booking.success; draft null
4. Имя: test_valid_code_creates_booking_and_redirects

Тест 11
1. Поведение: слот закрылся до ввода кода → redirect unavailable, запись не создана
2. Вход: слот закрыт после issue
3. Ожидание: redirect booking.unavailable; bookings 0
4. Имя: test_slot_closed_redirects_to_unavailable

Тест 12
1. Поведение: просроченный код → redirect code-expired; повторная отправка: раньше 60 с — отказ, после — новый код отправлен
2. Вход: код старше TTL; resend сразу; travelTo +60 с; resend
3. Ожидание: redirect code-expired; кулдаун; assertPushed после
4. Имя: test_expired_code_redirects_and_resend_has_cooldown

Тест 13  (экраны результата)
1. Поведение: success/unavailable/code-expired без пометки → главная; с пометкой — контент (детали записи, действия)
2. Вход: GET без/с flash-пометкой
3. Ожидание: redirect /; содержимое экранов
4. Имя: test_result_pages_require_marker / test_success_page_shows_booking_details
```

## Прогоны

**Красный** (до файлов реализации): `16 failed` — Class not found (Phone/verify/BookingCreator/экраны/маршруты), CodeStepPage-заглушка, миграции без cars ещё не было (сид на Car).

**Зелёный** (после реализации): `Tests: 71 passed (242 assertions)` — секции A (без cars), B (пронос), C (код/создание/экраны) + регресс всех прежних; pint clean.
