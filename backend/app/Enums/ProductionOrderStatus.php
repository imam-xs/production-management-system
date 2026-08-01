<?php

namespace App\Enums;

/**
 * Lifecycle of a production order.
 *
 * An order is created as Pending and only becomes Completed inside the same
 * transaction that moves the stock, so a Completed order always has consumption
 * rows and an output batch — there is no window where one exists without the
 * other.
 */
enum ProductionOrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Terminal states can never transition again.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Only a pending order may be executed — this is what makes a repeated
     * execute call a 409 rather than a double stock deduction.
     */
    public function canBeExecuted(): bool
    {
        return $this === self::Pending;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Pending;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
