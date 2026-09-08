@props(['active' => 1])

@php
    $steps = [1 => 'Время', 2 => 'Услуги', 3 => 'Данные', 4 => 'Подтверждение'];
@endphp

<div class="steps">
  @foreach ($steps as $number => $title)
    <span class="step {{ $number <= $active ? 'step--active' : '' }}">
      <span class="step-num">{{ $number }}</span>
      <span class="step-title">{{ $title }}</span>
    </span>
  @endforeach
</div>
