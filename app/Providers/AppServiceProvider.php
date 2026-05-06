<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\OrderRepositoryInterface::class,
            \App\Repositories\Eloquent\OrderRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\MenuItemRepositoryInterface::class,
            \App\Repositories\Eloquent\MenuItemRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
