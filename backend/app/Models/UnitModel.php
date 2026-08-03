<?php

namespace App\Models;

use Database\Factories\UnitModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitModel extends Model
{
    /** @use HasFactory<UnitModelFactory> */
    use HasFactory;

    // the class name no longer matches the table, so name it explicitly
    protected $table = 'units';

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * @return HasMany<ItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemModel::class, 'unit_id');
    }
}
