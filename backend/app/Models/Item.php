<?php

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ItemFactory;
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
 * @property-read ItemStock|null $stock The stock row is created lazily on an
 *   item's first inventory movement, so it is genuinely absent for a newly
 *   created item. The HasOne generic can't express that nullability, hence
 *   this explicit annotation.
 */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use SoftDeletes;

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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasOne<ItemStock, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(ItemStock::class);
    }

    /**
     * @return HasMany<Batch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Recipe lines describing what this item is made of.
     *
     * @return HasMany<BillOfMaterial, $this>
     */
    public function billOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'output_item_id');
    }

    /**
     * Recipe lines where this item is an ingredient.
     *
     * @return HasMany<BillOfMaterial, $this>
     */
    public function usedIn(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'input_item_id');
    }

    /**
     * @return HasMany<ProductionOrder, $this>
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'output_item_id');
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
