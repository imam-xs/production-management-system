<?php

namespace App\Enums;

/**
 * Reasons stock changes, recorded on the append-only inventory ledger.
 *
 * Every row in `inventory_movements` carries a signed quantity; the sign is
 * derived from the type rather than trusted from the caller, so the ledger can
 * always be re-summed to verify `item_stocks`.
 */
enum MovementType: string
{
    case Receipt = 'receipt';
    case ProductionInput = 'production_input';
    case ProductionOutput = 'production_output';
    case Adjustment = 'adjustment';

    /**
     * Direction this movement applies to stock. Adjustments carry their own
     * sign because they can go either way.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Receipt, self::ProductionOutput => 1,
            self::ProductionInput => -1,
            self::Adjustment => 0,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
