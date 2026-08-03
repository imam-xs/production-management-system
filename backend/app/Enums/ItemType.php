<?php

namespace App\Enums;

// the three production stages an item can belong to
enum ItemType: string
{
    case Raw = 'raw';
    case SemiFinished = 'semi_finished';
    case Finished = 'finished';

    // prefix used when generating batch numbers for this stage

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
