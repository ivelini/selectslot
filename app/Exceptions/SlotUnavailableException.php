<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Слот занят/закрыт к моменту подтверждения кода (ФТ-8): запись не создаётся,
 * клиенту предлагается выбрать другое время (экран unavailable).
 */
class SlotUnavailableException extends RuntimeException {}
