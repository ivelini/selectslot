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

## [2026-09-08] ingest | Шаг «Услуги» реализован; политика прайса (ADR 0007)
- Источники: реализация (PricingCalculator, ServicesStepPage, сид-куб 120, мокап services.html, ФТ v0.12)
- Updated: Цены (точное совпадение; нет правила = ошибка; 0 правил = база; ADR 0007)
- Updated: Публичный сайт (шаг «Услуги» реализован, Reader::isSelectableHour, RussianDate/Money)
- Updated: Схема БД (демо-данные: куб 120, воскресенье рабочее, горизонт сида 60, без обедов)
- Updated: Статичный мокап (services.html без «не знаю»/наценок; фактические цены сценария)

## [2026-09-09] ingest | Реальный прайс: оси правил без опций, цена за единицу и количество
- Источники: миграция v0.9, PricingCalculator, ServicesStepPage (счётчики), сид-прайс 135, ФТ v0.13, ADR 0007 (уточнение осей)
- Updated: Цены (оси услуга/радиус/тип, цена за единицу × количество, RunFlat/TPMS — доп. работы)
- Updated: Схема БД (опции удалены из price_rules/bookings/cars; booking_services.quantity; куб 135)
- Updated: Публичный сайт (шаг «Услуги»: счётчики 1–4, без опций)
- Updated: Статичный мокап (каталог по прайсу, сценарий 2 400 ₽, счётчики)

## [2026-09-09] ingest | Комплексы услуг: транзиентный выбор (ADR 0008)
- Источники: миграция complex_services/complex_service_item, toggleComplex, сид «Сезонный шиномонтаж», ФТ v0.14, db-schema v0.10
- Updated: Публичный сайт (шаг «Услуги»: комплексы, состояния none/partial/full)
- Updated: Схема БД (complex_services, complex_service_item; сид-комплекс)
- Updated: Цены (комплексы-наборы без цены в каталоге)
- Updated: Статичный мокап (блок «Готовые комплексы», сценарий 4 000 ₽)

## [2026-09-09] ingest | Живые цены карточек услуг (по радиусу/типу)
- Источник: ServicesStepPage (единый расчёт каталога → unit-цены карточек), мокап services.html
- Updated: Публичный сайт (карточки услуг показывают цену за единицу по выбранным параметрам)

## [2026-09-09] ingest | Шаг «Данные» реализован: контакты + запрос SMS-кода
- Источники: DetailsStepPage, BookingCodeService, SmsSender/LogSmsSender/SendBookingCodeSms, Phone-канон, мокап details.html
- Updated: Публичный сайт (шаг «Данные»: черновик сессии, issue, SMS через очередь)
- Updated: Создание записи (запрос кода на шаге данных; отправка через очередь; канон телефона)
- Updated: Схема БД (customers/booking_codes: канон телефона, code_hash)

## [2026-09-09] ingest | Шаг «Код» и создание записи; cars упразднены (ADR 0009)
- Источники: CodeStepPage + экраны результата, BookingCreator/verify, миграция drop cars + bookings.plate, сквозной пронос шага 1, ФТ v0.15, db-schema v0.11
- Updated: Создание записи (подтверждение реализовано: verify, BookingCreator, экраны с flash)
- Updated: Публичный сайт (шаг «Код», экраны, пронос выбора через смену времени)
- Updated: Схема БД (cars удалена, bookings.plate; 12 сущностей)

## [2026-09-09] ingest | Рефакторинг слоёв и конвенций; ADR 0010
- Источники: ADR 0010 (каталоги Actions/Services), рефакторинг сессии 09.09: CreateBookingAction/CalculatePriceAction/GenerateSlotGridAction (…Action::handle), Booking::forCode, SettingKeyEnum + Setting::get, кулдаун в config, SelectionStepPage (каркас шагов 2–4, подкаталог), BookingQuery, enum'ы в App\Enums\Booking, правила кодирования (глобальные/проектные), чек-лист перед коммитом в /ship
- Updated: Архитектура приложения (слои ADR 0010)
- Updated: Создание записи (CreateBookingAction, Booking::forCode, SettingKeyEnum)
- Updated: Цены (CalculatePriceAction)
- Updated: Слоты (GenerateSlotGridAction, SettingKeyEnum)
- Updated: Публичный сайт (SelectionStepPage, BookingQuery, неймспейсы шагов 2–4)
- Updated: Схема БД (enum'ы App\Enums\Booking)

## [2026-09-09] ingest | Бронь с сайта занимает час (ФТ v0.16, ADR 0003 уточнено)
- Источники: ФТ v0.16 (ФТ-6/ФТ-8/ФТ-16), ADR 0003 (уточнение), реализация: BookingSelection::$closeSlot, CreateBookingAction (закрытие слота с привязкой в транзакции), CodeStepPage closeSlot: true
- Updated: Слоты (модель ёмкости: одна бронь сайта на час, админка пишет в закрытый слот)
- Updated: Создание записи (броня занимает час — слот закрывается с привязкой)
