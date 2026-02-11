<?php

use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\Api\AuthController;

// Admin
use App\Http\Controllers\Api\Admin\AdminCardController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminPackageController;
use App\Http\Controllers\Api\Admin\AdminUserController;
// User
use App\Http\Controllers\Api\User\UserDashboardController;
use App\Http\Controllers\Api\User\UserOrderController;
use App\Http\Controllers\Api\User\UserPackageController;

// (اختياري) Packages/Cards browsing للـ user
// use App\Http\Controllers\Api\User\UserPackageController;

Route::prefix('backend')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::post('login', 'login');
            Route::post('register', 'register');

            // الأفضل: auth فقط بدون role قاسي
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('logout', 'logout');
                Route::get('me', 'me');
            });
        });
    });

    /**
     * =========================
     * Admin (admin only)
     * =========================
     */
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->prefix('admin')
        ->group(function () {

            // Users
            Route::middleware('permission:manage users')
                ->prefix('users')
                ->controller(AdminUserController::class)
                ->group(function () {
                    Route::post('index', 'index');       // list + filters
                    Route::get('{user}', 'show');        // details
                    Route::post('', 'store');            // create
                    Route::put('{user}', 'update');      // update
                    Route::delete('{user}', 'destroy');  // delete
                });

            // Categories
            Route::middleware('permission:manage categories')
                ->prefix('categories')
                ->controller(AdminCategoryController::class)
                ->group(function () {
                    Route::post('index', 'index');
                    Route::get('{category}', 'show');
                    Route::post('', 'store');
                    Route::put('{category}', 'update');
                    Route::delete('{category}', 'destroy');
                });

            // Packages
            Route::middleware('permission:manage packages')
                ->prefix('packages')
                ->controller(AdminPackageController::class)
                ->group(function () {
                    Route::post('index', 'index');
                    Route::get('{package}', 'show');
                    Route::post('', 'store');
                    Route::put('{package}', 'update');
                    Route::delete('{package}', 'destroy');
                    Route::get('{package}/cards', 'cards');
                });

            // Cards (افصل permissions الحساسة)
            Route::prefix('cards')
                ->controller(AdminCardController::class)
                ->group(function () {

                    Route::middleware('permission:manage cards')->group(function () {
                        Route::post('index', 'index');
                        Route::post('', 'store');
                        Route::get('{card}', 'show');
                        Route::delete('{card}', 'destroy');
                    });

                    Route::middleware('permission:import cards')
                        ->post('import', 'import');

                    Route::middleware('permission:view card credentials')
                        ->get('{card}/credentials', 'credentials');
                });

            // Orders
            Route::middleware('permission:manage orders')
                ->prefix('orders')
                ->controller(AdminOrderController::class)
                ->group(function () {
                    Route::post('index', 'index');
                    Route::get('{order}', 'show');
                    // Route::post('', 'store');

                    Route::get('{order}/confirm', 'confirm');
                    Route::get('{order}/cancel', 'cancel');
                });

            // Dashboard
            Route::middleware('permission:view admin dashboard')
                ->prefix('dashboard')
                ->controller(AdminDashboardController::class)
                ->group(function () {
                    Route::get('overview', 'overview');
                    Route::get('cards-status', 'cardsStatus');
                    Route::post('orders-timeseries', 'ordersTimeseries');
                    Route::post('latest-orders', 'latestOrders');
                    Route::get('packages-inventory', 'packagesInventory');
                });
        });

    /**
     * =========================
     * User (user only)
     * =========================
     */
    Route::middleware(['auth:sanctum', 'role:user'])
        ->prefix('user')
        ->group(function () {

            // Packages
            Route::controller(UserPackageController::class)
                ->prefix('packages')->group(function () {
                    Route::post('index', 'index')->middleware('permission:user view packages');
                    Route::get('{package}', 'show')->middleware('permission:user show package');
                    Route::get('{package}/cards', 'cards')->middleware('permission:user show cards package');
                });

            // Orders
            Route::controller(UserOrderController::class)
                ->prefix('orders')
                ->group(function () {
                    Route::get('index', 'index')->middleware('permission:user own orders');
                    Route::post('store', 'store')->middleware('permission:user create order');
                });

            // Dashboard
            Route::controller(UserDashboardController::class)
                ->prefix('dashboard')->group(function () {
                    Route::get('overview', 'overview')->middleware('permission:view user dashboard');
                });
        });
});
