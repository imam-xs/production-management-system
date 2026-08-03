<?php

namespace App\Events;

use App\Models\ProductionOrderModel;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

// raised when a production run finishes successfully
class ProductionCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ProductionOrderModel $order,
    ) {}
}
