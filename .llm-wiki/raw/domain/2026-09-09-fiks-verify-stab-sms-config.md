# Фикс verify при стаб-коде и перенос SMS-конфига (2026-09-09)

> Source: Код репозитория TireSlot (правки 09.09.2026): app/Services/BookingCodeService.php, app/Livewire/Booking/SelectionStepPage/CodeStepPage.php, config/sms.php, тесты
> Collected: 2026-09-09
> Published: 2026-09-09

Диагностика «повторная бронь не записывается без ошибок» (после введения механики «бронь занимает час», ФТ v0.16). Две причины:

- **Стаб-код + порядок строк в verify:** `config/sms.php` (для ручных тестов) выдаёт один и тот же код при каждом `issue` — в `booking_codes` копятся строки с одинаковым hash. `BookingCodeService::verify` брал `->first()` — старую использованную строку → статус Used → редирект success с существующей записью (`Booking::forCode`), новая бронь не создавалась «тихо». Фикс: `verify` берёт свежайшую выдачу `->latest('id')` — при коллизии hash проверяется новая активная строка (Valid); повторный ввод использованного кода без новой выдачи остаётся Used (НФ-1 — код создаёт ровно одну запись). Тест `test_verify_uses_latest_row_when_code_repeats`.
- **Рассинхрон конфига:** кулдаун повторной SMS переехал в `config/sms.php` (`sms.resend_cooldown_seconds`), а `CodeStepPage` читал `services.sms.*` (блок удалён из `config/services.php`) → null → кулдаун не срабатывал. Фикс: чтение `config('sms.resend_cooldown_seconds')`. Устаревшие упоминания `services.sms.driver` в докблоках (`Contracts\SmsSender`, `LogSmsSender`, `AppServiceProvider`) приведены к `config('sms.provider')`.

Поведение на закрытый час (первая броня) после фикса — видимый экран «Время недоступно» (SlotUnavailableException), а не тихий «успех» со старой записью.
