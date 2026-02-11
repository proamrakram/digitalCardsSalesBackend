<?php

namespace App\Providers;

use App\Models\Card;
use App\Models\Category;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Policies\CardPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PackagePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Policy mappings for the application.
     */

    protected array $policies = [
        Category::class => CategoryPolicy::class,
        Package::class  => PackagePolicy::class,
        Card::class     => CardPolicy::class,
        Order::class    => OrderPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            // لو admin خلي كل شيء مسموح
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }
            return null; // يكمل للـ policy العادي
        });
    }
}
