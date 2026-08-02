<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductionEventLogResource;
use App\Services\ProductionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// read-only view of what the RabbitMQ consumer has processed
// rows here are written by the worker process, never by an HTTP request, so this
// endpoint is the simplest way to demonstrate that the asynchronous path really
// runs — the admin UI renders it as an event feed
class ProductionEventController extends Controller
{
    public function __construct(
        private readonly ProductionService $production,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', '15'), 1), 100);

        return ProductionEventLogResource::collection($this->production->eventLog($perPage));
    }
}
