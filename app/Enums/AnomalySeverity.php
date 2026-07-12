<?php

namespace App\Enums;

enum AnomalySeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Human readable label for the severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
        };
    }
}
