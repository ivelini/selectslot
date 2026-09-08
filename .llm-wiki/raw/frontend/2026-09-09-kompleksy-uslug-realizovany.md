# Комплексы услуг (complex_services) реализованы: транзиентный выбор (2026-09-09)

> Source: Репозиторий TireSlot: миграция 2026_09_09_000001_create_complex_services_tables, app/Models/Service/ComplexService.php, app/Livewire/Booking/ServicesStepPage.php (toggleComplex), database/seeders/CatalogSeeder.php, ФТ v0.14, db-schema v0.10, ADR 0008
> Collected: 2026-09-09
> Published: 2026-09-09

Готовые комплексы услуг (например, «Сезонный шиномонтаж») — ADR 0008: транзиентная UI-механика. Ключевые факты:

- **Схема (db-schema v0.10):** `complex_services` (name, is_active) + `complex_service_item` (complex_service_id cascade, service_id restrict; unique-пара) — many-to-many. Комплекс без собственной цены; в URL, заявку и снимок записи не попадает — только состав (услуги с количествами).
- **Модель** `App\Models\Service\ComplexService` + `Service::complexes()` (belongsToMany через complex_service_item).
- **Сид**: «Сезонный шиномонтаж» = Снятие и установка колёс + Демонтаж колёс + Монтаж колёс + Балансировка колёс (updateOrCreate + sync в CatalogSeeder).
- **ServicesStepPage::toggleComplex(id)**: клик — состав полон и все услуги = 4 → снять услуги комплекса (вне-комплектные не трогаются); иначе → привести к комплекту ×4 (недостающие добавить, ВСЕ услуги комплекса установить в 4 — уже выбранные с другим qty переустанавливаются).
- **Состояние комплекса** (дериват выбора, в URL не пишется): none/partial/full — по вхождению услуг в serviceIds; view: блок «Готовые комплексы» с карточками (имя + состав · ×4), классы complex-card / --full / --partial.
- Мокап services.html: блок комплексов, сценарий: комплекс выбран, 4 работы ×4 (R16 кроссовер): 1 200 + 800 + 800 + 1 200 = **4 000 ₽**; CSS-секции (мокап + public).
- ФТ v0.14: комплекс в глоссарии, ФТ-1 (справочник админки), ФТ-4 (выбор комплекса отмечает входящие услуги ×4). ADR 0008.
