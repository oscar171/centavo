<?php

namespace App\Enums;

enum AnomalyType: string
{
    case DuplicateCharge = 'duplicate_charge';
    case ChargeBurst = 'charge_burst';
    case RecurringSubscription = 'recurring_subscription';
    case Reversal = 'reversal';
    case UnusualAmount = 'unusual_amount';
    case PossibleFraud = 'possible_fraud';

    /**
     * Human readable label for the anomaly type.
     */
    public function label(): string
    {
        return match ($this) {
            self::DuplicateCharge => 'Cargo duplicado',
            self::ChargeBurst => 'Ráfaga de cargos',
            self::RecurringSubscription => 'Suscripción recurrente',
            self::Reversal => 'Reverso / devolución',
            self::UnusualAmount => 'Monto inusual',
            self::PossibleFraud => 'Posible fraude',
        };
    }
}
