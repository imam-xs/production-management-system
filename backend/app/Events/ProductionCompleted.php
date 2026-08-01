<?php

namespace App\Events;

use App\Models\ProductionOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a production run finishes successfully.
 *
 * Implements ShouldDispatchAfterCommit deliberately: the dispatch is deferred
 * until the surrounding `DB::transaction` in ProductionService actually
 * commits. Publishing from inside the open transaction would risk a "phantom
 * event" — the consumer receiving a message about an order the database later
 * rolled back, and then failing to find it. Since execute() retries on
 * deadlock, that rollback is a real possibility, not a theoretical one.
 */
class ProductionCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ProductionOrder $order,
    ) {}
}
