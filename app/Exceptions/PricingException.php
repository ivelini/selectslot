<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Прайс-правило для комбинации (услуга, радиус, тип, опции) отсутствует,
 * хотя у услуги правила есть. Потерянное правило — баг данных, не повод
 * показывать неверную цену: расчёт падает, страница ловит и предлагает звонок.
 */
class PricingException extends RuntimeException {}
