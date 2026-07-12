<?php

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Credit = 'credit';

    /**
     * Human readable label for the account type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Corriente',
            self::Savings => 'Ahorros',
            self::Credit => 'Crédito',
        };
    }

    /**
     * The list of options exposed to the frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
