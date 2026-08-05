<?php

namespace App\Enums;

// reasons stock changes, recorded on the append-only inventory ledger
enum MovementType: string
{
    case Receipt = 'receipt';
    case ProductionInput = 'production_input';
    case ProductionOutput = 'production_output';
    case Adjustment = 'adjustment';

    // direction this movement applies to stock
    public function sign(): int
    {
        return match ($this) {
            self::Receipt, self::ProductionOutput => 1,
            self::ProductionInput => -1,
            self::Adjustment => 0,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
