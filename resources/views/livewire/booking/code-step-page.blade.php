<section class="booking-section container">
  <h2>Подтверждение записи</h2>
  <p class="booking-subtitle">Введите код из SMS — запись будет создана</p>

  <x-booking.steps :active="4" />

  <div class="code-card">
    <h3>Код из SMS</h3>
    @if ($phoneLabel !== '')
      <p class="code-subtitle">Мы отправили SMS с кодом на номер <span class="code-phone">{{ $phoneLabel }}</span></p>
    @endif

    @if ($notice !== '')
      <p class="field-success">{{ $notice }}</p>
    @endif
    @error('code')
      <p class="field-error">{{ $message }}</p>
    @enderror

    <div class="otp-inputs">
      @foreach (range(0, 3) as $i)
        <input
          class="otp-digit"
          type="text"
          inputmode="numeric"
          maxlength="1"
          wire:model="digits.{{ $i }}"
          x-on:input="$event.target.value = $event.target.value.replace(/\D/g, ''); if ($event.target.value !== '' && $event.target.nextElementSibling) $event.target.nextElementSibling.focus()"
          aria-label="Цифра кода {{ $i + 1 }}"
        />
      @endforeach
    </div>

    <p class="code-hint">
      Код действует 15 минут. Запись создастся только после ввода кода — время заранее не удерживается
    </p>

    @error('resend')
      <p class="field-error">{{ $message }}</p>
    @enderror
    <button
      type="button"
      class="code-resend"
      wire:click="resend"
      @disabled($resendDisabled)
    >Отправить код повторно</button>

    <button type="button" class="booking-btn-primary" wire:click="submit">Подтвердить запись</button>
  </div>

  <div class="code-summary">
    <span>{{ $dateLabel }}</span>
    <a class="code-summary-link" wire:navigate href="{{ route('booking.time', request()->query()) }}">Изменить время</a>
  </div>
</section>
