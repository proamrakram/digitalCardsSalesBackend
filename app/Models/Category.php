<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'name_ar',
        'type', // hourly, monthly
        'description',
    ];

    // relations, accessors, mutators, scopes can be added here
    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    // Build Filters Scope
    public function scopeFilter(Builder $builder, array $filters = [])
    {
        $filters = array_merge([
            'type' => null,
        ], $filters);

        if ($filters['type']) {
            $builder->where('type', $filters['type']);
        }

        return $builder;
    }
}
