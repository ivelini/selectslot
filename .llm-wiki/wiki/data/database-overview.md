# Схема БД: таблицы, связи, ключевые решения

> Sources: TireSlot db-schema v0.11, 2026-09-09; ФТ v0.15, 2026-09-09; Каркас проекта, 2026-09-08
> Raw: [db-schema v0.8](../../raw/domain/2026-09-08-db-schema.md); [ФТ v0.11](../../raw/domain/2026-09-08-functional-requirements.md); [Каркас реализован](../../raw/domain/2026-09-08-karkas-proekta-realizovan.md)

## Overview

Справочная карта БД TireSlot v1. Конвенции: snake_case, `timestamps` на всех таблицах, цены в копейках (`unsignedInteger`), enum-поля — string-колонки. Механики, стоящие за таблицами, описаны в статьях [domain](../domain/slot-grid.md), [booking-flow](../domain/booking-flow.md), [pricing](../domain/pricing.md).

## Таблицы

- **users** — сотрудники: name, email (unique), password, role (`admin`/`operator`). Источник `operator_id` записей.
- **customers** — клиенты: name, phone (unique, канон «7XXXXXXXXXX» — `App\Support\Phone`). Идентификация без регистрации. Сущности «автомобиль» нет (ADR 0009): параметры и госномер — снимок записей.
- **services** — услуги: name, category (`tire`/`storage`/`other`), is_active, base_price (fallback, если правила нет).
- **complex_services** — готовые комплексы: name, is_active; своей цены нет — в запись попадает состав.
- **complex_service_item** — состав комплекса: complex_service_id (cascade), service_id (restrict); `unique (complex_service_id, service_id)`.
- **price_rules** — ценовые правила: service_id, radius (R13–R21), car_type, price (за единицу). `unique (service_id, radius, car_type)`; подбор — [pricing](../domain/pricing.md).
- **schedule_templates** — шаблон недели: weekday (0–6, unique), open_time/close_time (null = выходной). Исключений на дату нет.
- **slots** — слоты: date, hour (0–23), is_closed, close_reason, booking_id (nullable — привязка закрытия к записи). `unique (date, hour)`. Генерация планировщиком — [slot-grid](../domain/slot-grid.md).
- **bookings** — записи: customer_id, slot_id, booking_code_id (nullable — код, подтвердивший запись; верификатор отмены; null — запись по звонку), start_time (время начала внутри слота), status, source (`site`/`admin`), cancel_reason, idempotency_key (uuid, unique), снимок radius/car_type/plate (госномер), total_price, operator_id (nullable). Индексы: (slot_id), (customer_id), (status), (booking_code_id).
- **booking_codes** — коды подтверждения: phone (канон), code_hash (sha256 кода + app.key; plaintext не хранится), used_at. Индекс (phone). Заявка не хранится (передаётся при вводе кода); TTL по created_at + reservation_timeout_min; просроченные неиспользованные удаляет крон.
- **booking_services** — состав записи: booking_id, service_id, price (снимок цены за единицу), quantity (1–4). `unique (booking_id, service_id)`.
- **settings** — параметры конфигурации (key/value): `reservation_timeout_min`, `cancel_free_before_h`, `min_lead_time_h`, `booking_horizon_days`, `shop_address`, `shop_phone`.

## Связи

```
users ──< bookings (operator_id)
customers ──< bookings
services ──< price_rules
services ──< booking_services >── bookings
slots ──< bookings (slot_id)
bookings ──< slots (booking_id, 0..1 — привязка закрытия)
schedule_templates — источник генерации slots
```

Запись видит все записи слота через `slot_id` (12:00 и 12:20 — один слот); лимита записей в слоте нет. Дата записи берётся из слота.

## Ключевые решения схемы

1. Слот — хранимая сущность: генерация планировщиком, закрытие флагом на строке; строки с записями и закрытые не удаляются (ADR 0001).
2. Запись привязана к слоту; создаётся только при подтверждении кода (из админки — сразу); гонка «подтверждение vs закрытие» сериализуется `SELECT … FOR UPDATE` строки слота, двойное создание исключено одноразовым кодом и `idempotency_key` (ADR 0002, НФ-1).
3. Снимок в записи: параметры авто и цены фиксируются при создании (ADR 0004).
4. Перерывы и исключения на дату — не сущности, выражаются закрытием слотов (ФТ-3, ФТ-16).
5. Счётчик неявок — вычисляется из `bookings (status = no_show)`, колонки нет.
6. Код подтверждения — только верификатор (`booking_codes`: телефон + хэш + одноразовость); заявка не хранится, запись создаётся из выбора клиента при вводе кода и привязывается к коду (`booking_code_id`) — код остаётся верификатором отмены.

## Реализация и демо-данные (2026-09-08)

Схема реализована миграциями и моделями (Laravel 13: атрибуты `#[Fillable]`, `casts()`); enum-классы в `app/Enums/` (+ `WheelRadiusEnum` R13–R21, `CarTypeEnum::bookable()`). Сиды (DatabaseSeeder) наполняют демо-окружение: пользователи `admin@tireslot.local` / `operator@tireslot.local` (пароль `password`), 10 услуг реального прайса (5 работ с полным кубом 135 правил: R13–21 × 3 типа; 5 допработ без правил) + комплекс «Сезонный шиномонтаж» (4 работы), шаблон — все дни 9:00–19:00 (воскресенье рабочее), горизонт сида 60 дней (fallback 30), записи на окно −7…+6 дней от today (расчёт через PricingCalculator). Копейки — `unsignedInteger`; производитель работ: `SlotGridGenerator` (см. [slot-grid](../domain/slot-grid.md)); расчёт цены — `PricingCalculator` (см. [Цены](../domain/pricing.md)).

## See Also

- [Слоты: сетка и закрытие](../domain/slot-grid.md)
- [Создание записи](../domain/booking-flow.md)
- [Статусная машина](../domain/booking-statuses.md)
- [Цены и снимок](../domain/pricing.md)
