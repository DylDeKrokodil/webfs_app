<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['token', 'table_code', 'order_ids', 'order_fingerprint', 'paid_at', 'submitted_at'])]
class ReviewInvite extends Model
{
    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    protected function casts(): array
    {
        return [
            'order_ids' => 'array',
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }
}
