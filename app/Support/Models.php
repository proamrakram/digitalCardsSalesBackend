<?php

namespace App\Support;

use Illuminate\Support\Str;

trait Models
{
    // Add UUID generation on creating event
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
