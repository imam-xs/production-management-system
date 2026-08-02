<?php

namespace App\Enums;

/**
 * The three production stages an item can belong to.
 *
 * Raw materials, semi-finished products and finished products share one table
 * because every downstream concern — batches, stock, movements, consumption
 * edges — is structurally identical for all three. The type is what gives each
 * its own independent inventory and its own REST resource.
 */
enum ItemType: string
{
    case Raw = 'raw';
    case SemiFinished = 'semi_finished';
    case Finished = 'finished';

    /**
     * Prefix used when generating batch numbers for this stage.
     */
    public function batchPrefix(): string
    {
        return match ($this) {
            self::Raw => 'RM',
            self::SemiFinished => 'SF',
            self::Finished => 'FG',
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
