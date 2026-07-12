<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    /**
     * Human readable label for the direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Ingreso',
            self::Debit => 'Egreso',
        };
    }
}
