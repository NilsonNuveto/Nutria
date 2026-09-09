<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where(function ($query): void {
                    $query->whereNotNull('source_id')->orWhere('user_id', auth()->id());
                });
            }
        });
    }

    protected $table = 'foods';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_values' => 'array', 'calories' => 'float', 'protein' => 'float', 'carbs' => 'float', 'fat' => 'float', 'fiber' => 'float', 'sodium' => 'float', 'base_quantity' => 'float'];
    }
}
