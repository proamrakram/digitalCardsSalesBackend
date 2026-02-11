<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'name_ar',
        'description',
        'duration',
        'price',
        'status', // active, inactive
        'type', // virtual, physical
        'category_id',
    ];

    // relations, accessors, mutators, scopes can be added here

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    // Build Filters Scope
    public function scopeFilter(Builder $builder, array $filters = [])
    {
        $filters = array_merge([
            'search' => null,
            'category_id' => null,
            'status' => [],
            'type' => [],
        ], $filters);

        if ($filters['search']) {
            $builder->where(function (Builder $query) use ($filters) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query->where('name', 'like', $searchTerm)
                    ->orWhere('uuid', 'like', $searchTerm)
                    ->orWhere('name_ar', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        if ($filters['category_id']) {
            $builder->where('category_id', $filters['category_id']);
        }

        if ($filters['type'] && is_array($filters['type'])) {
            $builder->whereIn('type', $filters['type']);
        }

        if (!empty($filters['status']) && is_array($filters['status'])) {
            $builder->whereIn('status', $filters['status']);
        }

        return $builder;
    }
}
