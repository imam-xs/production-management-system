<?php

namespace App\Enums;

// lifecycle of a production order
enum ProductionOrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    // only a pending order may be executed — this is what makes a repeated
    // execute call a 409 rather than a double stock deduction
    public function canBeExecuted(): bool
    {
        return $this === self::Pending;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
