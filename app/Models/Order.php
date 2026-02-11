<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'uuid',
        'payment_method', // BOP, cash, palpay
        'payment_proof_url',
        'amount',
        'price',
        'quantity',
        'total_price',
        'notes',
        'status', // pending, confirmed, cancelled
        'confirmed_at',
        'cancelled_at',
        'user_id',
        'cards',
        'package_id',
    ];

    // castings
    protected $casts = [
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cards' => 'array'
    ];

    // relations, accessors, mutators, scopes can be added here
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function scopeFilters(Builder $builder, array $filters = [])
    {
        $filters = array_merge([
            'search' => null,
            'status' => [],
            'payment_method' => [],
            'user_id' => null,
            'package_id' => null,
            'from' => null,
            'to' => null,
        ], $filters);

        if ($filters['search']) {
            $builder->where(function (Builder $query) use ($filters) {
                $searchTerm = '%' . $filters['search'] . '%';
                $query->where('uuid', 'like', $searchTerm)
                    ->orWhere('notes', 'like', $searchTerm);
            });
        }

        if (!empty($filters['status'])) {
            $builder->whereIn('status', (array) $filters['status']);
        }

        if (!empty($filters['payment_method'])) {
            $builder->whereIn('payment_method', (array) $filters['payment_method']);
        }

        if ($filters['user_id']) {
            $builder->where('user_id', $filters['user_id']);
        }

        if ($filters['package_id']) {
            $builder->where('package_id', $filters['package_id']);
        }

        // ✅ التاريخ داخل scope
        if (!empty($filters['from'])) {
            $builder->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $builder->whereDate('created_at', '<=', $filters['to']);
        }

        return $builder;
    }
}
