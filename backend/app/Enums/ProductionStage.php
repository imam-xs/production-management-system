<?php

namespace App\Enums;

// the two transformations the plant performs
enum ProductionStage: string
{
    case RawToSemiFinished = 'raw_to_semi_finished';
    case SemiFinishedToFinished = 'semi_finished_to_finished';

    public function outputType(): ItemType
    {
        return match ($this) {
            self::RawToSemiFinished => ItemType::SemiFinished,
            self::SemiFinishedToFinished => ItemType::Finished,
        };
    }

    public static function forOutputType(ItemType $type): ?self
    {
        return match ($type) {
            ItemType::SemiFinished => self::RawToSemiFinished,
            ItemType::Finished => self::SemiFinishedToFinished,
            ItemType::Raw => null,
        };
    }

    // event name recorded on the production event log, identifies which
    // transition the message describes
    public function routingKey(): string
    {
        return match ($this) {
            self::RawToSemiFinished => 'production.raw_to_semi.completed',
            self::SemiFinishedToFinished => 'production.semi_to_finished.completed',
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
