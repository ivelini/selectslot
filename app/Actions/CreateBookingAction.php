<?php

namespace App\Actions;

use App\Enums\Booking\BookingSourceEnum;
use App\Enums\Booking\BookingStatusEnum;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingCode;
use App\Models\Booking\BookingService;
use App\Models\Customer;
use App\Models\Service\Service;
use App\Models\Slot;
use App\ValueObjects\BookingSelection;
use App\ValueObjects\CustomerDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Создание записи при подтверждении кода (ФТ-8, ADR 0002, НФ-4) — единый Action
 * для сайта (и, в будущем, каналов админки). Атомарность (НФ-1): блокировка строки
 * слота `SELECT … FOR UPDATE` внутри транзакции исключает гонку «закрытие vs запись».
 */
class CreateBookingAction
{
    public function __construct(private readonly CalculatePriceAction $calculatePrice) {}

    /**
     * Создаёт запись из актуального выбора. Код к этому моменту верифицирован (Valid);
     * одноразовость кода — атомарная пометка used_at в той же транзакции.
     *
     * @throws SlotUnavailableException слот закрыт или строки нет
     */
    public function handle(BookingCode $code, CustomerDraft $draft, BookingSelection $selection): Booking
    {
        return DB::transaction(function () use ($code, $draft, $selection): Booking {
            $slot = Slot::query()
                ->whereDate('date', $selection->date)
                ->where('hour', $selection->hour)
                ->lockForUpdate()
                ->first();

            if ($slot === null || $slot->is_closed) {
                throw new SlotUnavailableException('Слот недоступен для записи');
            }

            $customer = Customer::firstOrCreate(
                ['phone' => $draft->phone],
                ['name' => $draft->name],
            );

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'slot_id' => $slot->id,
                'start_time' => sprintf('%02d:00:00', $selection->hour),
                'status' => BookingStatusEnum::Confirmed,
                'source' => BookingSourceEnum::Site,
                'booking_code_id' => $code->id,
                'idempotency_key' => Str::uuid(),
                'radius' => $selection->params->radius,
                'car_type' => $selection->params->carType,
                'plate' => $draft->plate,
                'total_price' => 0,
            ]);

            $services = Service::query()
                ->whereIn('id', array_keys($selection->quantities))
                ->where('is_active', true)
                ->get();

            // Серверный пересчёт из актуального выбора (ФТ-8): сумма клиентом не передаётся
            $quote = $this->calculatePrice->handle($services, $selection->params, $selection->quantities);

            foreach ($quote['lines'] as $line) {
                BookingService::create([
                    'booking_id' => $booking->id,
                    'service_id' => $line['service']->id,
                    'price' => $line['unit_price'], // снимок: цена за единицу
                    'quantity' => $line['quantity'],
                ]);
            }

            $booking->update(['total_price' => $quote['total']]);

            // Одноразовость кода — атомарно внутри транзакции с созданием записи
            $code->update(['used_at' => now()]);

            return $booking;
        });
    }
}
