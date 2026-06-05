<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_invite_id',
    'overall_score',
    'food_score',
    'service_score',
    'speed_score',
    'favorite_dish',
    'comment',
    'contact_permission',
    'metadata',
])]
class Review extends Model
{
    /**
     * @return BelongsTo<ReviewInvite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(ReviewInvite::class, 'review_invite_id');
    }

    protected function casts(): array
    {
        return [
            'contact_permission' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
