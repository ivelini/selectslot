<?php

namespace App\Livewire\Booking\SelectionStepPage;

use App\Actions\CreateBookingAction;
use App\Enums\CarTypeEnum;
use App\Enums\CodeStatusEnum;
use App\Exceptions\SlotUnavailableException;
use App\Jobs\SendBookingCodeSms;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingCode;
use App\Services\BookingCodeService;
use App\Services\SlotAvailabilityReader;
use App\Support\RussianDate;
use App\ValueObjects\BookingSelection;
use App\ValueObjects\CustomerDraft;
use App\ValueObjects\VehicleParams;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

/**
 * Шаг 4 «Подтверждение» (мокап booking/code.html): ввод SMS-кода → создание записи (ФТ-8).
 *
 * Экраны результата — редиректы с сессионной пометкой (ADR 0006): success,
 * unavailable (слот занят), code-expired (TTL). Повторная отправка кода — с кулдауном.
 */
#[Layout('layouts.public')]
class CodeStepPage extends SelectionStepPage
{
    /** @var list<string> четыре цифры кода */
    public array $digits = ['', '', '', ''];

    public string $notice = '';

    /** Код отправляли на телефон из черновика: без черновика шаг «Код» бессмыслен. */
    protected function afterMount(): void
    {
        if (! is_array(session('booking_draft'))) {
            $this->redirect($this->detailsUrl());
        }
    }

    public function submit(BookingCodeService $codes, CreateBookingAction $createBooking, SlotAvailabilityReader $reader): void
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
            CodeStatusEnum::Used => $this->redirectToExisting($verification->code),
            CodeStatusEnum::Valid => $this->confirmBooking($verification->code, $createBooking, $reader),
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
            : (int) $lastSentAt->diffInSeconds(now()) - config('services.sms.resend_cooldown_seconds');

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

    private function confirmBooking(?BookingCode $code, CreateBookingAction $createBooking, SlotAvailabilityReader $reader): void
    {
        $draft = session('booking_draft');
        if ($code === null || ! is_array($draft)) {
            $this->addError('code', 'Неверный код — проверьте SMS');

            return;
        }

        $carType = CarTypeEnum::tryFrom((string) $this->carType);
        $selected = $this->selectionDateTime();
        if ($selected === null || $carType === null || $this->radius === null) {
            $this->addError('code', 'Выбор устарел — вернитесь к выбору услуг');

            return;
        }

        try {
            $booking = $createBooking->handle(
                $code,
                new CustomerDraft(
                    (string) ($draft['name'] ?? ''),
                    (string) ($draft['phone'] ?? ''),
                    isset($draft['plate']) && $draft['plate'] !== '' ? (string) $draft['plate'] : null,
                ),
                new BookingSelection(
                    date: $selected['date']->toDateString(),
                    hour: $selected['hour'],
                    params: new VehicleParams($this->radius, $carType),
                    quantities: $this->quantities,
                    // Бронь с сайта занимает час: слот закрывается с привязкой к записи (ФТ-8/ФТ-16)
                    closeSlot: true,
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

    private function redirectToExisting(?BookingCode $code): void
    {
        // Повторный submit уже использованного кода: запись создана ранее (НФ-1)
        $booking = $code !== null ? Booking::forCode($code) : null;
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

        return max(0, config('services.sms.resend_cooldown_seconds') - (int) $lastSentAt->diffInSeconds(now()));
    }

    private function formatPhone(string $canonical): string
    {
        return '+7 ('.substr($canonical, 1, 3).') '.substr($canonical, 4, 3).'-'.substr($canonical, 7, 2).'-'.substr($canonical, 9, 2);
    }

    private function detailsUrl(): string
    {
        return route('booking.details', $this->selectionQueryParams());
    }

    private function dateLabel(): string
    {
        $date = CarbonImmutable::parse($this->date);

        return RussianDate::dayShort($date).', '.$this->time;
    }
}
