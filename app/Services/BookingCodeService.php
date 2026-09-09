<?php

namespace App\Services;

use App\Enums\CodeStatusEnum;
use App\Enums\Settings\SettingKeyEnum;
use App\Models\Booking\BookingCode;
use App\Models\Setting;
use App\Support\Phone;
use App\ValueObjects\CodeVerification;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Коды подтверждения (ФТ-7/ФТ-9/ФТ-23): строка на телефон с одноразовым кодом.
 * Код хранится только хэшем (sha256 кода + ключ приложения); plaintext нужен
 * лишь для SMS и не сохраняется. Заявка не хранится (ADR 0002).
 */
class BookingCodeService
{
    /**
     * Создаёт строку кода и возвращает plain-код для отправки по SMS.
     * Повторный запрос не аннулирует старые активные коды телефона (TTL — крон).
     */
    public function issue(string $phone): string
    {
        $canonical = Phone::normalize($phone);
        if ($canonical === null) {
            throw new InvalidArgumentException("Невалидный телефон: {$phone}");
        }

        $code = $this->buildCode();

        BookingCode::create([
            'phone' => $canonical,
            'code_hash' => $this->hash($code),
        ]);

        return $code;
    }

    private function buildCode(): int
    {
        if (config('sms.stub')) {
            return config('sms.stub.code');
        }

        return (string) random_int(1000, 9999);
    }

    /**
     * Проверяет код телефона: Valid (активен, в TTL) / Used (уже создал запись —
     * повторный submit вернёт существующую, НФ-1) / Expired (TTL истёк) / Invalid.
     */
    public function verify(string $phone, string $code): CodeVerification
    {
        $canonical = Phone::normalize($phone);
        $row = $canonical === null
            ? null
            : BookingCode::query()
                ->where('phone', $canonical)
                ->where('code_hash', $this->hash($code))
                // Свежайшая выдача с этим кодом: при совпадающих hash (стаб-код для ручных тестов)
                // first() брал старую использованную строку и повторная бронь «тихо» уходила в Used
                ->latest('id')
                ->first();

        if ($row === null) {
            return new CodeVerification(CodeStatusEnum::Invalid);
        }

        if ($row->used_at !== null) {
            return new CodeVerification(CodeStatusEnum::Used, $row);
        }

        if ($row->created_at->lt(now()->subMinutes(Setting::get(SettingKeyEnum::ReservationTimeoutMin)))) {
            return new CodeVerification(CodeStatusEnum::Expired, $row);
        }

        return new CodeVerification(CodeStatusEnum::Valid, $row);
    }

    /** Последний (самый свежий) код телефона — для кулдауна повторной отправки. */
    public function lastIssuedAt(string $phone): ?CarbonImmutable
    {
        $canonical = Phone::normalize($phone);

        return $canonical === null ? null : BookingCode::query()
            ->where('phone', $canonical)
            ->latest('id')
            ->value('created_at');
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code.config('app.key'));
    }
}
