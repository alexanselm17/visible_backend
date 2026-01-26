<?php

namespace App\Providers;

use App\Repositories\Auth\AuthRepository;
use App\Repositories\Auth\AuthRepositoryInterface;
use App\Repositories\Customers\CustomerRepository;
use App\Repositories\Customers\CustomerRepositoryInterface;
use App\Repositories\Products\ProductRepository;
use App\Repositories\Products\ProductRepositoryInterface;
use App\Repositories\Sales\SalesRepository;
use App\Repositories\Sales\SalesRepositoryInterface;
use App\Repositories\Setup\SetupRepository;
use App\Repositories\Setup\SetupRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(SalesRepositoryInterface::class, SalesRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(SetupRepositoryInterface::class, SetupRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
