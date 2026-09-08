<?php

namespace App\Livewire\Booking;

use App\Enums\CarTypeEnum;
use App\Enums\CodeStatusEnum;
use App\Enums\WheelRadiusEnum;
use App\Exceptions\SlotUnavailableException;
use App\Jobs\SendBookingCodeSms;
use App\Models\Booking\BookingCode;
use App\Models\Service\Service;
use App\Services\BookingCodeService;
use App\Services\BookingCreator;
use App\Services\SlotAvailabilityReader;
use App\Support\RussianDate;
use App\ValueObjects\BookingSelection;
use App\ValueObjects\CustomerDraft;
use App\ValueObjects\VehicleParams;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Шаг 4 «Подтверждение» (мокап booking/code.html): ввод SMS-кода → создание записи (ФТ-8).
 *
 * Экраны результата — редиректы с сессионной пометкой (ADR 0006): success,
 * unavailable (слот занят), code-expired (TTL). Повторная отправка кода — с кулдауном.
 */
#[Layout('layouts.public')]
class CodeStepPage extends Component
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):00$/';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    /** @var list<int> */
    #[Url(as: 'services')]
    public array $serviceIds = [];

    /** @var array<int, int> */
    #[Url(as: 'quantities')]
    public array $quantities = [];

    #[Url]
    public ?int $radius = null;

    #[Url(as: 'car_type')]
    public ?string $carType = null;

    /** @var list<string> четыре цифры кода */
    public array $digits = ['', '', '', ''];

    public string $notice = '';

    public function mount(SlotAvailabilityReader $reader): void
    {
        $date = $this->parseDate($this->date);
        $hour = $this->parseTimeHour($this->time);
        if ($date === null
            || $hour === null
            || ! $reader->isWithinBookingWindow($date)
            || ! $reader->isSelectableHour($date, $hour)) {
            $this->redirect(route('booking.time'));

            return;
        }

        $this->serviceIds = $this->filterActiveServiceIds($this->serviceIds);
        $this->quantities = $this->normalizeQuantities($this->quantities);
        $this->radius = $this->radius !== null ? WheelRadiusEnum::tryFrom($this->radius)?->value : null;
        $this->carType = $this->carTypeValueOrNull($this->carType);

        // Код отправляли на телефон из черновика: без черновика шаг «Код» бессмыслен
        if (! is_array(session('booking_draft'))) {
            $this->redirect($this->detailsUrl());

            return;
        }
    }

    public function submit(BookingCodeService $codes, BookingCreator $creator): void
    {
        $draft = session('booking_draft');
        $phone = is_array($draft) ? (string) ($draft['phone'] ?? '') : '';
        $entered = implode('', $this->digits);

        if (preg_match('/^\d{4}$/', $entered) !== 1) {
            $this->addError('code', 'Введите код из SMS');

            return;
        }

        $verification = $codes->verify($phone, $entered);

        match ($verification->status) {
            CodeStatusEnum::Invalid => $this->addError('code', 'Неверный код — проверьте SMS'),
            CodeStatusEnum::Expired => $this->redirectToExpired(),
            CodeStatusEnum::Used => $this->redirectToExisting($verification->code, $creator),
            CodeStatusEnum::Valid => $this->confirmBooking($verification->code, $creator, $draft),
        };
    }

    /** Повторная отправка кода (ФТ-23): не чаще раза в 60 секунд. */
    public function resend(BookingCodeService $codes): void
    {
        $draft = session('booking_draft');
        $phone = is_array($draft) ? (string) ($draft['phone'] ?? '') : '';

        $lastSentAt = $codes->lastIssuedAt($phone);
        $cooldownLeft = $lastSentAt === null
            ? 0
            : (int) $lastSentAt->diffInSeconds(now()) - BookingCodeService::RESEND_COOLDOWN_SECONDS;

        if ($cooldownLeft < 0) {
            $this->addError('resend', 'Код уже отправлен. Повторите через '.abs($cooldownLeft).' секунд');

            return;
        }

        $newCode = $codes->issue($phone);
        SendBookingCodeSms::dispatch($phone, $newCode);
        $this->notice = 'Новый код отправлен';
        $this->digits = ['', '', '', ''];
    }

    public function render(SlotAvailabilityReader $reader): View
    {
        $draft = session('booking_draft');
        $phone = is_array($draft) ? (string) ($draft['phone'] ?? '') : '';
        $resendWait = $this->resendWaitSeconds($phone);

        return view('livewire.booking.code-step-page', [
            'phoneLabel' => $phone === '' ? '' : $this->formatPhone($phone),
            'resendWait' => $resendWait,
            'resendDisabled' => $resendWait > 0,
            'dateLabel' => $this->date !== null ? $this->dateLabel() : null,
        ]);
    }

    private function confirmBooking(?BookingCode $code, BookingCreator $creator, mixed $draft): void
    {
        if ($code === null || ! is_array($draft)) {
            $this->addError('code', 'Неверный код — проверьте SMS');

            return;
        }

        $carType = CarTypeEnum::tryFrom((string) $this->carType);
        $date = $this->parseDate($this->date);
        $hour = $this->parseTimeHour($this->time);
        if ($date === null || $hour === null || $carType === null || $this->radius === null) {
            $this->addError('code', 'Выбор устарел — вернитесь к выбору услуг');

            return;
        }

        try {
            $booking = $creator->confirm(
                $code,
                new CustomerDraft(
                    (string) ($draft['name'] ?? ''),
                    (string) ($draft['phone'] ?? ''),
                    isset($draft['plate']) && $draft['plate'] !== '' ? (string) $draft['plate'] : null,
                ),
                new BookingSelection(
                    date: $date->toDateString(),
                    hour: $hour,
                    params: new VehicleParams($this->radius, $carType),
                    quantities: $this->quantities,
                ),
            );
        } catch (SlotUnavailableException) {
            session()->flash('booking_unavailable', true);
            $this->redirect(route('booking.unavailable'));

            return;
        }

        session()->forget('booking_draft');
        session()->flash('booking_success_id', $booking->id);
        $this->redirect(route('booking.success'));
    }

    private function redirectToExpired(): void
    {
        session()->flash('booking_code_expired', true);
        $this->redirect(route('booking.code-expired'));
    }

    private function redirectToExisting(?BookingCode $code, BookingCreator $creator): void
    {
        // Повторный submit уже использованного кода: запись создана ранее (НФ-1)
        $booking = $code !== null ? $creator->bookingForCode($code) : null;
        if ($booking === null) {
            $this->addError('code', 'Неверный код — проверьте SMS');

            return;
        }

        session()->forget('booking_draft');
        session()->flash('booking_success_id', $booking->id);
        $this->redirect(route('booking.success'));
    }

    private function resendWaitSeconds(string $phone): int
    {
        $lastSentAt = app(BookingCodeService::class)->lastIssuedAt($phone);
        if ($lastSentAt === null) {
            return 0;
        }

        return max(0, BookingCodeService::RESEND_COOLDOWN_SECONDS - (int) $lastSentAt->diffInSeconds(now()));
    }

    private function formatPhone(string $canonical): string
    {
        return '+7 ('.substr($canonical, 1, 3).') '.substr($canonical, 4, 3).'-'.substr($canonical, 7, 2).'-'.substr($canonical, 9, 2);
    }

    private function detailsUrl(): string
    {
        return route('booking.details', [
            'date' => $this->date,
            'time' => $this->time,
            'services' => $this->serviceIds,
            'quantities' => $this->quantities,
            'radius' => $this->radius,
            'car_type' => $this->carType,
        ]);
    }

    private function dateLabel(): string
    {
        $date = CarbonImmutable::parse($this->date);

        return RussianDate::dayShort($date).', '.$this->time;
    }

    /** @param  list<int>  $ids */
    private function filterActiveServiceIds(array $ids): array
    {
        return Service::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $quantities
     * @return array<int, int>
     */
    private function normalizeQuantities(array $quantities): array
    {
        $normalized = [];
        foreach ($this->serviceIds as $serviceId) {
            $raw = $quantities[$serviceId] ?? 4;
            $normalized[$serviceId] = max(1, min(4, (int) $raw));
        }

        return $normalized;
    }

    private function carTypeValueOrNull(?string $value): ?string
    {
        $carType = CarTypeEnum::tryFrom((string) $value);

        return $carType !== null && in_array($carType, CarTypeEnum::bookable(), true) ? $carType->value : null;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match(self::DATE_PATTERN, $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function parseTimeHour(?string $value): ?int
    {
        if ($value === null || preg_match(self::TIME_PATTERN, $value) !== 1) {
            return null;
        }

        return (int) substr($value, 0, 2);
    }
}
