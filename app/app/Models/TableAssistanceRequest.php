<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['table_code', 'resolved_at'])]
class TableAssistanceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
