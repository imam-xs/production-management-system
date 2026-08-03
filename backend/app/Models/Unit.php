<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
