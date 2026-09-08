<section class="booking-section container">
  <div class="code-card">
    <h3>Код из SMS</h3>
    <div class="alert alert--error">
      <img src="{{ asset('assets/img/warning.svg') }}" alt="" />
      <div>
        <p class="alert-title">Код устарел</p>
        <p class="alert-text">
          Срок действия кода — 15 минут — истёк. Запись не создана. Запросите новый код на тот же номер
          или введите другой номер телефона.
        </p>
      </div>
    </div>
    <a class="booking-btn-primary" wire:navigate href="{{ $codeUrl ?? route('booking.code') }}">Запросить новый код</a>
  </div>
</section>
