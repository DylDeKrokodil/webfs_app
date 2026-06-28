<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'tour_key', 'tour_version', 'current_step', 'completed_at', 'dismissed_at'])]
class UserTourProgress extends Model
{
    protected $table = 'user_tour_progress';

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
