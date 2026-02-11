<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'uuid',
        'username',
        'password',
        'status', // available, reserved, sold
        'reserved_at',
        'sold_at',
        'package_id',
        'user_id',
    ];

    // castings
    protected $casts = [
        'reserved_at' => 'datetime',
        'sold_at' => 'datetime',
    ];

    // Builder Scope for Filtering
    public function scopeFilter(Builder $builder, array $filters = [])
    {
        $filters = array_merge([
            'search' => null, // search by username or uuid
            'package_id' => null,
            'user_id' => null,
            'status' => [],
            'reserved_at' => null,
            'sold_at' => null,
        ], $filters);

        if ($filters['search']) {
            $builder->where(function (Builder $query) use ($filters) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query->where('username', 'like', $searchTerm)
                    ->orWhere('uuid', 'like', $searchTerm);
            });
        }

        if ($filters['package_id']) {
            $builder->where('package_id', $filters['package_id']);
        }

        if ($filters['user_id']) {
            $builder->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $builder->whereIn('status', (array) $filters['status']);
        }

        if ($filters['reserved_at']) {
            $builder->whereDate('reserved_at', $filters['reserved_at']);
        }

        if ($filters['sold_at']) {
            $builder->whereDate('sold_at', $filters['sold_at']);
        }

        return $builder;
    }

    // relations, accessors, mutators, scopes can be added here
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
