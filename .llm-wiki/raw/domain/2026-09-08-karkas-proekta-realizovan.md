# Каркас проекта реализован: миграции, модели, enum, генератор слотов, сиды (2026-09-08)

> Source: Код репозитория TireSlot (коммит каркаса): app/, database/, docker/php/Dockerfile
> Collected: 2026-09-08
> Published: 2026-09-08

Реализация схемы db-schema v0.6 и механик каркаса. Ключевые факты реализации:

- Enum-классы: `app/Enums/` — RoleEnum, ServiceCategoryEnum, CarTypeEnum, BookingStatusEnum, BookingSourceEnum (string-backed, метод label() — русские подписи).
- Модели (Laravel 13 стиль: атрибуты #[Fillable]/#[Hidden], casts()): Customer, Car, Service, PriceRule, ScheduleTemplate, Slot, Booking, BookingCode, BookingService (явная модель состава, не pivot), Setting (key PK), User (role).
- Генератор сетки: `App\Services\SlotGridGenerator` + команда `slots:generate` в расписании Laravel каждые 15 минут (ADR 0001). Идемпотентный insertOrIgnore, удаляет только открытые пустые строки вне шаблона, прошлое не пересматривает; горизонт из settings.booking_horizon_days (fallback 30).
- Сидеры (DatabaseSeeder по графу): UserSeeder (admin@tireslot.local / operator@tireslot.local, пароль password), CatalogSeeder (8 услуг: Снятие/установка колёс 600 ₽, Балансировка 400 ₽...; 36 прайс-правил для снятия/установки: R13–18 × легковая/кроссовер/внедорожник × RunFlat), ScheduleSeeder (Пн–Сб 9:00–19:00), SlotSeeder (генератор + закрытие слотов 13:00 «Обед» на горизонте), SettingsSeeder (6 ключей: reservation_timeout_min=15, cancel_free_before_h=2, min_lead_time_h=1, booking_horizon_days=30, shop_address, shop_phone), DemoBookingSeeder (18 клиентов/25 авто, записи на окно −7…+6 дней от today: прошлое — done/cancelled/no_show, будущее — confirmed; целые часы = source site, минуты 15/30/45 = admin; слоты прошлого создаются явно — генератор в прошлое не пишет).
- Фабрики: Customer/Car/Service/Booking (+User) для тестов.
- Технические решения окружения: Dockerfile — добавлен pdo_pgsql; phpredis собирается из GitHub (pecl.php.net недоступен). Laravel 13: now() → CarbonImmutable; даты сравнивать через whereDate (sqlite-совместимость); цены — копейки (unsignedInteger).
