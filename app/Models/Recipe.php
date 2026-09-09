<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ingredients' => 'array'];
    }
}
