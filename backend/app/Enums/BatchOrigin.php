<?php

namespace App\Enums;

// how a batch came into existence

enum BatchOrigin: string
{
    case Purchase = 'purchase';
    case Production = 'production';

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
