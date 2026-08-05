<?php

namespace App\Providers;

use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\ProductionOrderRepositoryInterface;
use App\Repositories\Eloquent\BatchRepository;
use App\Repositories\Eloquent\BillOfMaterialRepository;
use App\Repositories\Eloquent\InventoryRepository;
use App\Repositories\Eloquent\ItemRepository;
use App\Repositories\Eloquent\ProductionOrderRepository;
use Illuminate\Support\ServiceProvider;

// which class the container builds when a service asks for a repository interface
class RepositoryServiceProvider extends ServiceProvider
{
    private const BINDINGS = [
        ItemRepositoryInterface::class => ItemRepository::class,
        BatchRepositoryInterface::class => BatchRepository::class,
        ProductionOrderRepositoryInterface::class => ProductionOrderRepository::class,
        InventoryRepositoryInterface::class => InventoryRepository::class,
        BillOfMaterialRepositoryInterface::class => BillOfMaterialRepository::class,
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $abstract => $concrete) {
            // singleton, not bind: repositories hold no state, so every class
            // that asks for one in a request can safely share the same instance
            $this->app->singleton($abstract, $concrete);
        }
    }
}
