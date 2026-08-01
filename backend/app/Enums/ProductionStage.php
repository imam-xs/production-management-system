<?php

namespace App\Enums;

/**
 * The two transformations the plant performs.
 *
 * Each stage declares which item type it consumes and which it produces, so
 * legality is data rather than a chain of conditionals. Supporting a third
 * stage (finished -> packaged) means adding a case here plus a strategy, with
 * no edits to the services that consume it.
 */
enum ProductionStage: string
{
    case RawToSemiFinished = 'raw_to_semi_finished';
    case SemiFinishedToFinished = 'semi_finished_to_finished';

    public function label(): string
    {
        return match ($this) {
            self::RawToSemiFinished => 'Raw Material → Semi-Finished',
            self::SemiFinishedToFinished => 'Semi-Finished → Finished',
        };
    }

    public function inputType(): ItemType
    {
        return match ($this) {
            self::RawToSemiFinished => ItemType::Raw,
            self::SemiFinishedToFinished => ItemType::SemiFinished,
        };
    }

    public function outputType(): ItemType
    {
        return match ($this) {
            self::RawToSemiFinished => ItemType::SemiFinished,
            self::SemiFinishedToFinished => ItemType::Finished,
        };
    }

    /**
     * The stage implied by producing the given item type, or null when that
     * type is never produced (raw materials are received, not manufactured).
     */
    public static function forOutputType(ItemType $type): ?self
    {
        return match ($type) {
            ItemType::SemiFinished => self::RawToSemiFinished,
            ItemType::Finished => self::SemiFinishedToFinished,
            ItemType::Raw => null,
        };
    }

    /**
     * AMQP routing key published when a run of this stage completes.
     *
     * Both match the consumer's binding key `production.*.completed`, so a new
     * stage is picked up by the existing queue without a topology change.
     */
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
