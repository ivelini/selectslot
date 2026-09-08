# Схема БД: таблицы, связи, ключевые решения

> Sources: TireSlot db-schema v0.6, 2026-09-04; ФТ v0.10, 2026-09-04; Каркас проекта, 2026-09-08
> Raw: [db-schema v0.6](../../raw/domain/2026-09-04-db-schema.md); [ФТ v0.10](../../raw/domain/2026-09-04-functional-requirements.md); [Каркас реализован](../../raw/domain/2026-09-08-karkas-proekta-realizovan.md)

## Overview

Справочная карта БД TireSlot v1. Конвенции: snake_case, `timestamps` на всех таблицах, цены в копейках (`unsignedInteger`), enum-поля — string-колонки. Механики, стоящие за таблицами, описаны в статьях [domain](../domain/slot-grid.md), [booking-flow](../domain/booking-flow.md), [pricing](../domain/pricing.md).

## Таблицы

- **users** — сотрудники: name, email (unique), password, role (`admin`/`operator`). Источник `operator_id` записей.
- **customers** — клиенты: name, phone (unique). Идентификация без регистрации.
- **cars** — автомобили: customer_id, plate (nullable), radius (R13–R22, nullable), car_type (`passenger`/`crossover`/`suv`/`truck`, nullable), has_runflat, has_tpms. Индекс (customer_id).
- **services** — услуги: name, category (`tire`/`storage`/`other`), is_active, base_price (fallback, если правила нет).
- **price_rules** — ценовые правила: service_id, radius, car_type, has_runflat, has_tpms, price. `unique (service_id, radius, car_type, has_runflat, has_tpms)`; подбор — [pricing](../domain/pricing.md).
- **schedule_templates** — шаблон недели: weekday (0–6, unique), open_time/close_time (null = выходной). Исключений на дату нет.
- **slots** — слоты: date, hour (0–23), is_closed, close_reason, booking_id (nullable — привязка закрытия к записи). `unique (date, hour)`. Генерация планировщиком — [slot-grid](../domain/slot-grid.md).
- **bookings** — записи: customer_id, car_id (nullable), slot_id, start_time (время начала внутри слота), status, source (`site`/`admin`), cancel_reason, confirmation_code_hash (верификатор отмены), idempotency_key (uuid, unique — защита от двойного сабмита), снимок radius/car_type/has_runflat/has_tpms, total_price, operator_id (nullable). Индексы: (slot_id), (customer_id), (status).
- **booking_codes** — заявки/коды: phone, code_hash, payload (json — снимок заявки), expires_at, used_at. Индекс (phone); просроченные удаляет крон.
- **booking_services** — состав записи: booking_id, service_id, price (на момент записи). `unique (booking_id, service_id)`.
- **settings** — параметры конфигурации (key/value): `reservation_timeout_min`, `cancel_free_before_h`, `min_lead_time_h`, `booking_horizon_days`, `shop_address`, `shop_phone`.

## Связи

```
users ──< bookings (operator_id)
customers ──< cars ──< bookings (car_id)
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
6. Заявка виджета живёт в `booking_codes`; при подтверждении `code_hash` переносится в запись как верификатор отмены.

## Реализация и демо-данные (2026-09-08)

Схема реализована миграциями и моделями (Laravel 13: атрибуты `#[Fillable]`, `casts()`); enum-классы в `app/Enums/`. Сиды (DatabaseSeeder) наполняют демо-окружение: пользователи `admin@tireslot.local` / `operator@tireslot.local` (пароль `password`), 8 услуг и 36 прайс-правил (демо-цены из мокапа), шаблон Пн–Сб 9:00–19:00, слоты на 30 дней с закрытыми обедами 13:00, записи на окно −7…+6 дней от today. Копейки — `unsignedInteger`; производитель работ: `SlotGridGenerator` (см. [slot-grid](../domain/slot-grid.md)); демо-подбор цены в сиде — временная копия, уйдёт в PricingService.

## See Also

- [Слоты: сетка и закрытие](../domain/slot-grid.md)
- [Создание записи](../domain/booking-flow.md)
- [Статусная машина](../domain/booking-statuses.md)
- [Цены и снимок](../domain/pricing.md)
