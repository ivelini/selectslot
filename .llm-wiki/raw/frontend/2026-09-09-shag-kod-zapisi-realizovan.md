# Шаг «Код» и создание записи реализованы; cars упразднены; сквозной пронос шага 1 (2026-09-09)

> Source: Репозиторий TireSlot: app/Livewire/Booking/{CodeStepPage,BookingSuccessPage,BookingUnavailablePage,BookingCodeExpiredPage}.php, app/Services/{BookingCreator,BookingCodeService}.php, app/ValueObjects/{BookingSelection,CustomerDraft,CodeVerification}.php, app/Enums/CodeStatusEnum.php, app/Exceptions/SlotUnavailableException.php, миграция 2026_09_09_000002, .template/booking/{code,success,unavailable,code-expired}.html, ФТ v0.15, db-schema v0.11, ADR 0009
> Collected: 2026-09-09
> Published: 2026-09-09

Завершение клиентского потока записи (шаги 1–4 + экраны результата). Ключевые факты:

- **Подтверждение (ФТ-8)**: `BookingCodeService::verify(phone, code)` → `CodeVerification` {CodeStatusEnum Valid/Used/Expired/Invalid, BookingCode?}: хэш-сверка, used-статус, TTL = created_at + reservation_timeout_min (15). `BookingCreator::confirm(code, CustomerDraft, BookingSelection)` — единый Action (НФ-4): транзакция, `SELECT … FOR UPDATE` строки слота (закрыт/нет → SlotUnavailableException), Customer firstOrCreate по канону-телефону, Booking confirmed со снимком (radius/car_type/plate, total — серверный пересчёт PricingCalculator, цена за единицу × quantity в booking_services), code.used_at атомарно; повторный submit использованного кода → Used → существующая запись по booking_code_id (идемпотентность, НФ-1). Черновик booking_draft очищается после успеха.
- **CodeStepPage**: OTP-ввод (4 поля), без черновика → шаг «Данные»; Invalid — ошибка на форме; Expired → code-expired; слот занят → unavailable; повторная отправка — кулдаун 60 с (по последнему коду телефона).
- **Экраны результата (ADR 0006)**: маршруты /booking/success|unavailable|code-expired — редиректы с flash-пометкой (booking_success_id/booking_unavailable/booking_code_expired), id записи в URL нет; вход без пометки → главная. Success: детали записи (№ TS-000123-формат, услуги ×N, параметры, итог; «Моя запись»-кнопка опущена — ветки нет).
- **Cars упразднены (ADR 0009)**: миграция drop cars, bookings.car_id → bookings.plate (nullable); Customer::cars/Car/CarFactory удалены; BookingFactory и демо-сид без авто (демо: профиль параметров клиента в памяти — эмуляция подстановки «из последней записи», ФТ-18/22). db-schema v0.11, ФТ v0.15.
- **Сквозной пронос шага 1**: TimeStepPage несёт services/quantities/radius/car_type из query в ссылку «К выбору услуг» — смена времени не теряет выбор (мокап unavailable: «услуги и данные сохранятся»).
- Мокап: Livewire-перенос code/success/unavailable/code-expired; согласие/«Моя запись»-кнопка опущены до появления ветки my-booking.
- Тесты: BookingCodeService verify (4 статуса), BookingCreator (confirm/слот закрыт/Used-идемпотентность), CodeStepPage (6), ResultPages (4), DatabaseSeeder (без cars), TimeStepPage (пронос, HTTP). Красный 16 failed → зелёный 71 passed (242 assertions).
