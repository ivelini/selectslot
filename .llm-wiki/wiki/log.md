# Wiki Log

## [2026-09-08] ingest | Инициализация wiki: документы проекта TireSlot
- Создано: Слоты: сетка времени, генерация, закрытие (domain/slot-grid.md)
- Создано: Создание записи: заявка → код → подтверждение (domain/booking-flow.md)
- Создано: Статусная машина записи и операторские операции (domain/booking-statuses.md)
- Создано: Цены: прайс-правила, расчёт, снимок в записи (domain/pricing.md)
- Создано: Схема БД: таблицы, связи, ключевые решения (data/database-overview.md)
- Создано: Архитектура приложения: монолит Laravel, каналы записи, границы v1 (architecture/application-architecture.md)
- Создано: Статичный мокап публичного сайта (.template/) (frontend/public-site-mockup.md)
- Источники: ФТ v0.10, db-schema v0.6 (documentations/, 2026-09-04), ADR 0001–0005, template-mockup.md

## [2026-09-08] ingest | Архитектура приложения: монолит Laravel, каналы записи, границы v1
- Источник: ADR 0006 (documentations/adr/, Accepted 2026-09-08)
- Updated: Архитектура приложения (раздел «Публичный сайт: страницы на шаг, состояние заявки»)

## [2026-09-08] ingest | Каркас проекта реализован: миграции, модели, генератор, сиды
- Updated: Слоты: сетка времени, генерация, закрытие (реализация SlotGridGenerator + slots:generate)
- Updated: Схема БД (раздел «Реализация и демо-данные»: enum-классы, сиды, демо-доступы)

## [2026-09-08] ingest | Схема v0.8: код без заявки, запись привязана к коду
- Источники: ФТ v0.11, db-schema v0.8 (documentations/, 2026-09-08)
- Updated: Создание записи (заявка не хранится; цена — серверный пересчёт при подтверждении)
- Updated: Схема БД (booking_codes: phone/code_hash/used_at; bookings.booking_code_id вместо confirmation_code_hash)
- Updated: Цены (снимок фиксируется при подтверждении, не при запросе кода)

## [2026-09-08] ingest | Публичный сайт: маршруты ветки и шаг «Время»
- Источник: реализация (routes/web.php, app/Livewire/Booking/, SlotAvailabilityReader, планы)
- Updated: Архитектура приложения (каркас маршрутов + шаг «Время» реализованы)
- Updated: Слоты (доступность для показа — SlotAvailabilityReader, whereDate)
- Updated: Статичный мокап публичного сайта (See Also на Livewire-реализацию)
