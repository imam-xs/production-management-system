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

/**
 * The single place where abstractions meet implementations.
 *
 * Services type-hint the interfaces only, so swapping an implementation — a
 * caching decorator, an in-memory fake for tests — is a one-line change here and
 * nothing else in the application moves. That is the dependency-inversion half of
 * the design, made concrete.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
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
            // Singleton: repositories are stateless, so one instance per request
            // is enough and avoids re-resolving them per injection point.
            $this->app->singleton($abstract, $concrete);
        }
    }
}
