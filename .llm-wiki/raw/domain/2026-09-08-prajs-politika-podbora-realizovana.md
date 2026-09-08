# Политика прайса реализована: полный куб правил, отсутствие комбинации — ошибка (2026-09-08)

> Source: Репозиторий TireSlot: app/Services/PricingCalculator.php, app/ValueObjects/VehicleParams.php, app/Exceptions/PricingException.php, app/Enums/{WheelRadiusEnum,CarTypeEnum}.php, database/seeders/CatalogSeeder.php, ADR 0007, ФТ v0.12
> Collected: 2026-09-08
> Published: 2026-09-08

Реализация политики прайса (ADR 0007, ФТ v0.12 ФТ-2/ФТ-4). Ключевые факты:

- **PricingCalculator** (App\Services): `quote(услуги, VehicleParams)` — единый расчёт для показа и подтверждения (НФ-4). Подбор **только точным совпадением** (услуга, радиус, тип, runflat, tpms); у услуги 0 правил → `base_price` (цена не зависит от параметров); правила есть, комбинации нет → `PricingException` (fail fast: потерянное правило — баг данных, не молчаливая база и не смягчение опций). Возврат: `{lines: [{service, price}], total}` — копейки.
- **VehicleParams** (App\ValueObjects) — DTO (radius int, CarTypeEnum, hasRunflat, hasTpms); радиус и тип у клиента обязательны («не знаю» нет).
- **WheelRadiusEnum** (int, R13=13…R22=22, label 'R13…') — радиусы сайта; **CarTypeEnum::bookable()** — типы сайта: легковая/кроссовер/внедорожник (без Truck — грузовики по звонку, ФТ-18).
- **Сид CatalogSeeder**: полный куб правил «Снятие/установка колёс» — R13–22 × 3 типа × runflat×2 × tpms×2 = **120 правил** (было 36, R13–18, только runflat). Демо-формула: база 600 ₽, +250 ₽ за радиус > R15, +150 ₽ кроссовер / +350 ₽ внедорожник, +300 ₽ RunFlat, +400 ₽ TPMS; truck-правил нет.
- **UI при PricingException** (шаг «Услуги»): «Цена недоступна — позвоните в мастерскую», «Продолжить» заблокирован (не 500).
- ФТ v0.12: ФТ-2 (полный набор правил параметрных услуг; отсутствие — ошибка конфигурации), ФТ-4 (радиус/тип обязательны, без «не знаю»; грузовики — по звонку). ADR 0007.
