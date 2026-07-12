<?php

namespace App\Enums;

enum TransactionCategory: string
{
    case Income = 'income';
    case Housing = 'housing';
    case Utilities = 'utilities';
    case Food = 'food';
    case Transport = 'transport';
    case Shopping = 'shopping';
    case Entertainment = 'entertainment';
    case Subscriptions = 'subscriptions';
    case Health = 'health';
    case Travel = 'travel';
    case Transfers = 'transfers';
    case Fees = 'fees';
    case Other = 'other';

    /**
     * The human-readable Spanish label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Income => 'Ingresos',
            self::Housing => 'Vivienda',
            self::Utilities => 'Servicios',
            self::Food => 'Comida',
            self::Transport => 'Transporte',
            self::Shopping => 'Compras',
            self::Entertainment => 'Entretenimiento',
            self::Subscriptions => 'Suscripciones',
            self::Health => 'Salud',
            self::Travel => 'Viajes',
            self::Transfers => 'Transferencias',
            self::Fees => 'Comisiones',
            self::Other => 'Otros',
        };
    }

    /**
     * Resolve a display label for a stored category value: the localized label
     * for a predefined category, or the raw value itself for a custom category
     * the user named. Returns null for an empty category.
     */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /**
     * The catalog shaped as select options for the frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }

    /**
     * Every category value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
