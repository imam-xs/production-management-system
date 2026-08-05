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

    protected $table = 'units';

    protected $fillable = [
        'code',
        'name',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ItemModel::class, 'unit_id');
    }
}
