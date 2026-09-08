# Каркас проекта: миграции, модели, enum, генератор слотов, сидеры

- **Дата:** 2026-09-08
- **Источник схемы:** `documentations/db-schema.md` v0.6 (11 таблиц); правила домена — ФТ v0.10, ADR 0001–0004, 0006
- **Статус:** согласован план; тест-лист — на подтверждение

## Цель

Заложить исполняемый каркас Laravel 13 (docker, postgres): схема БД миграциями, модели с отношениями и enum-кастами, enum-классы, генератор сетки слотов (ADR 0001) с командой и расписанием, фабрики и сидеры с демо-данными «на месяц слотов, записи клиентов на неделю вокруг today».

## Блоки (порядок реализации)

1. **Enum-классы** (`app/Enums/`): RoleEnum, ServiceCategoryEnum, CarTypeEnum, BookingStatusEnum, BookingSourceEnum — string-backed, TitleCase-ключи, `label()` (рус.). Переходы статусов в enum НЕ живут — отдельный класс статус-машины позже (SRP, решение).
2. **Миграции** (2026_09_08_*): правим scaffold `users` (+role); `customers`; `cars`; `services`; `price_rules`; `schedule_templates`; `slots` (unique date+hour); `bookings` (FK customer/car/slot/operator; индексы slot_id/customer_id/status; unique nullable idempotency_key); **alter**: FK `slots.booking_id → bookings` (разрыв цикла); `booking_codes`; `booking_services` (unique пара); `settings` (key PK). Копейки — unsignedInteger. Именование FK/индексов — конвенция Laravel.
3. **Модели** (`app/Models/`) + касты + отношения: Customer 1—* Car, 1—* Booking; Booking belongsTo customer/car(null)/slot/operator(null), hasMany items (BookingService); Slot hasMany bookings + belongsTo booking (привязка закрытия, null); Service hasMany priceRules; BookingService belongsTo service; BookingCode — плоская (payload → array); ScheduleTemplate — плоская; Setting key/value. Enum-касты — `asEnum`. Состав записи — явная модель BookingService (решение).
4. **КРАСНЫЙ прогон**: тест-файлы (только `tests/`), классы реализации не созданы → показать падение.
5. **Генератор слотов**: `app/Services/SlotGridGenerator` (горизонт из settings.booking_horizon_days; рабочие часы из schedule_templates; идемпотентный upsert, сохраняет is_closed/close_reason/booking_id; строки вне шаблона удаляет только открытые и без записей; даты < today не трогает) + команда `slots:generate` + расписание каждые 15 мин в `bootstrap/app.php` (запуск по крону — вопрос деплоя, в каркасе только регистрация).
6. **Фабрики**: CustomerFactory, CarFactory, BookingFactory (state по статусам), ServiceFactory. (`database/factories/`)
7. **Сидеры** (порядок — граф): users (admin/operator) → services (≈10, демо-цены по мокапу) → price_rules (набор R13–R18/типы/опции, +250 кроссовер +150 RunFlat +300) → schedule_templates (Пн–Сб 9:00–19:00, Вс off) → **слоты через SlotGridGenerator на 30 дней** → закрыть обеды 13:00 будней (демо единого механизма) → settings (7 ключей: 5 параметров + адрес/телефон из мокапа) → демо-клиенты (≈15, авто R13–R18) с записями: прошлая неделя — done/cancelled/no_show/arrived, вперёд — confirmed/arrived; плотность 2–3 записи в час (12:00, 12:30), 1–2 записи вне сетки. Даты — от today (решение).
8. **ЗЕЛЁНЫЙ прогон** + `make fresh` и ручные проверки.

## Ожидаемый результат

`make fresh` поднимает рабочую БД: справочники, слоты на 30 дней (будни 9–18, обеды закрыты), записи клиентов недели вокруг today со статусами; `slots:generate` идемпотентен; касты enum работают (`booking->status === BookingStatusEnum::Confirmed`).

## Тест-лист (на подтверждение пользователем)

── tests/Unit/Services/SlotGridGeneratorTest.php ──────

Тест 1
1. Поведение: первый прогон создаёт слоты на горизонт из шаблона: рабочие дни × часы [open, close), выходные без слотов
2. Вход: шаблон Пн–Сб 9:00–19:00, Вс off; settings.booking_horizon_days=30; пустая БД
3. Ожидание: слоты = будние даты из [today, today+29] × часы 9..18 (10/день), is_closed=false
4. Имя: test_creates_slots_on_horizon_from_weekly_schedule

Тест 2
1. Поведение: повторный прогон идемпотентен — число строк и их состояние не меняются
2. Вход: БД после Теста 1
3. Ожидание: повторный прогон → то же число строк, все флаги прежние (генерация идемпотентна, ADR 0001)
4. Имя: test_second_run_is_idempotent

Тест 3
1. Поведение: закрытый слот (is_closed) и причина закрытия переживают повторный прогон
2. Вход: слот завтра 14:00 закрыт оператором (is_closed=1, close_reason), повторный прогон
3. Ожидание: слот остаётся с is_closed=1 и той же причиной (upsert сохраняет состояние закрытия)
4. Имя: test_keeps_closed_slots_on_regeneration

Тест 4
1. Поведение: слот с записью вне актуального шаблона не удаляется (записи не трогаем)
2. Вход: строка 20:00 завтра с привязанной записью; шаблон рабочий до 19:00; прогон
3. Ожидание: строка 20:00 осталась (удаление только открытых и без записей)
4. Имя: test_keeps_out_of_schedule_slot_with_booking

Тест 5
1. Поведение: открытый пустой слот вне шаблона удаляется (час стал нерабочим, ADR 0001: удаление, если открыт и без записей)
2. Вход: строка 20:00 завтра открытая, без записей; шаблон рабочий до 19:00; прогон
3. Ожидание: строки 20:00 нет
4. Имя: test_removes_empty_open_slot_outside_schedule

Тест 6
1. Поведение: строки в прошлом (date < today) генератор не трогает
2. Вход: слот вчера 20:00 открытый без записей (исторический), прогон
3. Ожидание: строка вчера 20:00 осталась (прошлое — история, не пересматривается)
4. Имя: test_does_not_touch_past_slots

Тест 7
1. Поведение: горизонт берётся из settings (booking_horizon_days), а не захардкожен
2. Вход: settings.booking_horizon_days=7
3. Ожидание: последняя строка — today+6, строк today+7 нет
4. Имя: test_horizon_reads_from_settings

── tests/Feature/DatabaseSeederTest.php ──────

Тест 8
1. Поведение: сид завершается и наполняет БД: справочники, слоты и записи на неделю есть
2. Вход: пустая БД, запуск DatabaseSeeder
3. Ожидание: users ≥ 2; services ≥ 8; price_rules > 0; schedule_templates = 7; slots ≥ 100; bookings ≥ 10; среди bookings есть confirmed и done; закрытых слотов > 0 (обеды)
4. Имя: test_seeder_populates_database

Тест 9
1. Поведение: сид создаёт записи «неделя вокруг today»: есть записи с датой < today (история: done/cancelled/no_show) и датой >= today (confirmed/arrived)
2. Вход: результат сида
3. Ожидание: существуют bookings со start на дате >= today в статусе confirmed, и bookings со статусом done на датах < today
4. Имя: test_seeder_has_bookings_around_today_with_history

## Прогоны

**Красный** (2026-09-08, до создания app-классов): `9 failed, 2 passed` — 7× SlotGridGeneratorTest `BindingResolutionException` (класс не существует) + 2× DatabaseSeederTest (сид не наполняет контракт).

**Зелёный** (2026-09-08): `11 passed (28 assertions)` — Unit 7/7 (генератор), Feature 2/2 (сиды). `make fresh` на postgres: миграции 14/14, сиды все DONE (users 2, customers 18, cars 25, services 8, price_rules 36, slots 320 из них 26 закрытых-обеды, bookings 41, booking_services 69, settings 6).

Замечания по ходу (зафиксировать в коде/памяти): Laravel 13 `now()` → CarbonImmutable (типизировать через CarbonInterface); date-каст Eloquent хранит datetime-строку — сравнения по датам только через `whereDate` (sqlite); Dockerfile: добавлен pdo_pgsql, phpredis из GitHub (pecl.php.net недоступен из build-контекста).

## Закрытие плана

Ключевые решения плана → ADR при закрытии (спросить пользователя): переиспользование генератора сидом/кроном (ADR 0001 уже покрывает), enum-касты и SRP-машина статусов — решение уровня кода, кандидат не очевиден.
