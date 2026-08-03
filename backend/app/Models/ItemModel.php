<?php

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ItemModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A material or product at any of the three production stages.
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property ItemType $type
 * @property int $unit_id
 * @property string|null $description
 * @property string $reorder_level
 * @property bool $is_active
 * @property-read ItemStockModel|null $stock The stock row is created lazily on an
 *   item's first inventory movement, so it is genuinely absent for a newly
 *   created item. The HasOne generic can't express that nullability, hence
 *   this explicit annotation.
 */
class ItemModel extends Model
{
    /** @use HasFactory<ItemModelFactory> */
    use HasFactory;

    use SoftDeletes;

    // the class name no longer matches the table, so name it explicitly
    protected $table = 'items';

    protected $fillable = [
        'sku',
        'name',
        'type',
        'unit_id',
        'description',
        'reorder_level',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'reorder_level' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<UnitModel, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitModel::class);
    }

    /**
     * @return HasOne<ItemStockModel, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(ItemStockModel::class, 'item_id');
    }

    /**
     * @return HasMany<BatchModel, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(BatchModel::class, 'item_id');
    }

    /**
     * @return HasMany<InventoryMovementModel, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovementModel::class, 'item_id');
    }

    /**
     * Recipe lines describing what this item is made of.
     *
     * @return HasMany<BillOfMaterialModel, $this>
     */
    public function billOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterialModel::class, 'output_item_id');
    }

    /**
     * Recipe lines where this item is an ingredient.
     *
     * @return HasMany<BillOfMaterialModel, $this>
     */
    public function usedIn(): HasMany
    {
        return $this->hasMany(BillOfMaterialModel::class, 'input_item_id');
    }

    /**
     * @return HasMany<ProductionOrderModel, $this>
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrderModel::class, 'output_item_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, ItemType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
