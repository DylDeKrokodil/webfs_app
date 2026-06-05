<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'menu_category_id',
    'number',
    'suffix',
    'name',
    'description',
    'price',
    'legacy_menu_id',
    'is_active',
])]
class MenuItem extends Model
{
    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasOne<FavoriteMenuItem, $this>
     */
    public function favoriteStats(): HasOne
    {
        return $this->hasOne(FavoriteMenuItem::class);
    }
}

