<?php

namespace App\Repositories\Eloquent;

use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<BillOfMaterial>
 */
class BillOfMaterialRepository extends BaseRepository implements BillOfMaterialRepositoryInterface
{
    protected function model(): BillOfMaterial
    {
        return new BillOfMaterial;
    }

    public function recipeFor(Item $outputItem): Collection
    {
        return $this->query()
            ->with(['inputItem.unit', 'inputItem.stock'])
            ->where('output_item_id', $outputItem->id)
            // Ordered by input_item_id, not insertion id: ProductionService
            // locks each input item's stock in this order, so two orders that
            // share overlapping inputs always attempt their locks in the same
            // relative sequence, which is what keeps MySQL deadlocks between
            // them rare (DB::transaction's retry is the actual guarantee).
            ->orderBy('input_item_id')
            ->get();
    }

    public function hasRecipe(Item $outputItem): bool
    {
        return $this->query()->where('output_item_id', $outputItem->id)->exists();
    }

    public function create(array $attributes): BillOfMaterial
    {
        return $this->persist($attributes);
    }

    public function delete(BillOfMaterial $line): void
    {
        $line->delete();
    }
}
