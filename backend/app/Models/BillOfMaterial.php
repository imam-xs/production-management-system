<?php

namespace App\Models;

use Database\Factories\BillOfMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recipe line: how much of `input_item` one unit of `output_item` needs.
 *
 * @property int $id
 * @property int $output_item_id
 * @property int $input_item_id
 * @property string $quantity_per_unit
 */
class BillOfMaterial extends Model
{
    /** @use HasFactory<BillOfMaterialFactory> */
    use HasFactory;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'output_item_id',
        'input_item_id',
        'quantity_per_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_per_unit' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'input_item_id');
    }
}
