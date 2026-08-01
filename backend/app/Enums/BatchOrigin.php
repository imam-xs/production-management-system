<?php

namespace App\Enums;

/**
 * How a batch came into existence.
 *
 * This is what terminates the traceability recursion: a Purchase batch is a
 * leaf (it originated outside the plant), while a Production batch always has a
 * production order and therefore upstream consumption edges to follow.
 */
enum BatchOrigin: string
{
    case Purchase = 'purchase';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchased / Received',
            self::Production => 'Manufactured In-House',
        };
    }

    public function isTraceable(): bool
    {
        return $this === self::Production;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
