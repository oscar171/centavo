<?php

namespace App\Enums;

enum StatementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'Procesando',
            self::Processed => 'Procesado',
            self::NeedsReview => 'Requiere revisión',
            self::Failed => 'Falló',
        };
    }

    /**
     * Whether the statement is still being worked on by the queue.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
