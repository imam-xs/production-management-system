<?php

namespace App\Models;

use Database\Factories\BillOfMaterialModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// one recipe line: how much of `input_item` one unit of `output_item` needs
/** @property string $quantity_per_unit */
class BillOfMaterialModel extends Model
{
    /** @use HasFactory<BillOfMaterialModelFactory> */
    use HasFactory;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'output_item_id',
        'input_item_id',
        'quantity_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_unit' => 'decimal:4',
        ];
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'output_item_id');
    }

    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'input_item_id');
    }
}
