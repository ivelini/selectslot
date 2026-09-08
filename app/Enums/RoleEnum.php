<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Operator => 'Оператор',
        };
    }
}
