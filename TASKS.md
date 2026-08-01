# Production Management System — Implementation Task List

**Assignment window:** 30 Jul 2026 → 05 Aug 2026
**Deliverable:** public GitHub repo — source, docker-compose, migrations + seeders, README with setup & API docs

> **Git is owned by the user.** Repo creation, staging, commits, branches, and
> pushing are all handled manually — no git commands are run as part of these
> tasks.

---

## 1. Stack & Architecture Decisions

Every row here is a decision that must be defensible in the interview.

| Area | Choice | Rationale |
|---|---|---|
| Framework | Laravel 12, PHP 8.3 | Required stack |
| Database | MySQL 8 | Required stack |
| Messaging | RabbitMQ 3 (management image) via `php-amqplib`, custom artisan consumer | Explicit exchange / queue / binding / DLQ topology is what the "RabbitMQ integration" criterion rewards. The `laravel-queue-rabbitmq` driver hides AMQP behind Laravel's queue abstraction, leaving nothing to demonstrate |
| Product modeling | Single typed `items` table, `type` enum: `raw` / `semi_finished` / `finished` | Batches, inventory movements and consumption edges stay uniform instead of three near-identical table sets; traceability becomes one recursive walk regardless of stage. Three separate controllers + Request classes still expose three distinct REST resources exactly as specified |
| Inventory mutation | Synchronous, inside `DB::transaction` with `SELECT … FOR UPDATE`; RabbitMQ consumer handles side-effects only | The spec lists "updating inventory" as a possible consumer job, but async deduction makes "prevent production if inventory is insufficient" unenforceable — two concurrent orders both pass the check before either worker runs. Documented explicitly in the README as a considered deviation |
| Stock tracking | Per-batch `quantity_remaining` + FIFO allocation, with `item_stocks` as a read cache written in the same transaction | A single `quantity` column per product makes batch-level traceability impossible |
| Quantities | `decimal(15,4)` everywhere | Floats accumulate rounding drift in stock ledgers |
| Auth | Sanctum token auth — login/logout/me, seeded admin, `auth:sanctum` on writes. No roles, no policies | Gives `FormRequest::authorize()` real work and makes `created_by` meaningful. Roles/permissions are a full extra day on something not requested |
| Frontend | Separate Vite + React + TS SPA, API-only communication. Not Inertia, not Blade | Proves the REST API is genuinely standalone and reviewable without the UI running |
| Web server | Apache + mod_php inside the backend image — **no nginx container** | php-fpm would need a second container and a vhost file kept in sync with it. mod_php serves Laravel directly, so the whole Docker surface is two files next to the app they build, and the stack drops to four services |
| `final` keyword | **Not used.** Only `abstract` where extension is actually intended | Laravel's own stubs use plain `class`, so `final` is not a framework convention. It also blocks PHPUnit/Mockery from mocking a class directly, and no controller in this app is extended anyway (the one shared base is `abstract`) |
| `declare(strict_types=1)` | **Not used** | Also not a Laravel default. The explicit casts it originally forced (`$request->boolean()`, `(int)`) are kept, because converting query-string values deliberately is correct regardless |
| Automated tests | **Not a deliverable.** Correctness is verified by driving the real HTTP API end to end | The assignment's evaluation criteria don't include tests. Effort goes to requirement coverage and the Docker path instead — see §7 |
| DTOs | **None.** Data crosses layers as explicit typed method parameters, called with PHP named arguments | The layering asked for is Request → Service → Repository; a DTO layer is a fifth moving part. Our payloads are 3–5 fields, so a DTO's main pay-off (many fields surviving many hops) never materialises. Trade-off accepted knowingly: named parameters give type safety and IDE completion, but not the immutability a `readonly` DTO would. Sort-column whitelisting moved into `ItemRepository` so `?sort_by=` still cannot reach SQL |

---

## 2. Repository Layout

```
production-management-system/
├── backend/                 Laravel 12 — API only, no Blade views
│   ├── app/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Requests/
│   │   │   ├── Resources/
│   │   │   └── Middleware/
│   │   ├── Listeners/
│   │   ├── Messaging/        publisher interface + RabbitMQ impl + topology
│   │   ├── Models/
│   │   ├── Providers/
│   │   ├── Repositories/
│   │   │   ├── Contracts/
│   │   │   └── Eloquent/
│   │   └── Services/
│   │       └── Production/   stage strategies, allocator, batch numbering
│   ├── database/{migrations,factories,seeders}/
│   ├── routes/api.php
│   └── tests/{Feature,Unit}/
│   ├── Dockerfile            Apache + mod_php; serves both roles
│   └── docker-entrypoint.sh  role-aware provisioning
├── frontend/                Vite + React + TS — own package.json
├── docker-compose.yml
├── TASKS.md
└── README.md
```

### Docker services

Four services. `app` and `worker` run the same image, distinguished only by
`APP_ROLE`, so their code and PHP extensions can never drift apart.

| Service | Port | Notes |
|---|---|---|
| `app` | 8000 → 80 | Apache + mod_php, Laravel API, curl healthcheck on `/up` |
| `worker` | — | same image, `php artisan rabbitmq:consume`, `restart: unless-stopped` |
| `mysql` | 3307 → 3306 | named volume, healthcheck (3307 avoids a local MySQL clash) |
| `rabbitmq` | 5672 / 15672 | management UI on 15672 |
| `frontend` | 5173 | Vite dev server, `VITE_API_URL=http://localhost:8000/api/v1` |

---

## 3. Layering Convention

```
Route
  └─ Controller          thin — no business logic, no Eloquent
       ├─ FormRequest     ALL validation + authorize()
       ├─ Service         orchestration, transaction boundary, domain rules
       │                  (typed method parameters, no DTO layer)
       │    └─ RepositoryInterface → EloquentRepository (only place Eloquent lives)
       └─ API Resource    ALL response shaping
```

**No DTO layer.** A FormRequest validates, the controller pulls the validated
values out, and the service receives them as typed parameters. See the decisions
table for why.

**Plain `class` declarations** — no `final`, no `declare(strict_types=1)`.
`abstract` is used only where a class is genuinely meant to be extended
(`ItemController`, `StoreItemRequest`, `UpdateItemRequest`, `BaseRepository`,
`DomainException`). Query-string values are still cast explicitly at the
controller boundary; that was worth keeping on its own merits.

**Repository rule ("if needed"):** interface + implementation for entities with
real query complexity — `Item`, `Batch`, `ProductionOrder`, `InventoryMovement`
(locking, recursion, filtered listings). No repository for trivial lookup or
append-only tables (`Unit`, `ProductionEventLog`); the service uses the model
directly. Deliberate asymmetry — be ready to justify it.

**SOLID, concretely:**
- **S** — `ProductionService` orchestrates, `InventoryAllocator` picks batches, `BatchNumberGenerator` names them, `RabbitMqPublisher` ships events. One reason to change each
- **O** — a new stage (finished → packaged) is a new `ProductionStage` strategy, no edits to existing services
- **L** — repository impls honor their contracts; a caching decorator drops in transparently
- **I** — narrow per-entity interfaces, never one fat repository contract
- **D** — services type-hint interfaces only; bindings in `RepositoryServiceProvider`

---

## 4. Requirement Coverage Matrix

Cross-check before submitting — every assignment bullet maps to a task.

| Assignment requirement | Task |
|---|---|
| Raw → semi → finished workflow | 3.6 |
| Independent inventory per stage | 1.4, 1.10, 3.3 |
| Batches identified by number / qty / timestamp | 1.7 |
| Which raw materials + qty → each semi batch | 1.8 |
| Which semi batches → each finished batch | 1.8 |
| Deduct consumed input inventory | 3.8 |
| Add produced output inventory | 3.8 |
| Prevent production on insufficient stock | 3.5, 6.3 |
| Trace finished → semi → raw | 2.7, 3.9, 6.5 |
| CRUD × 3 product types | 4.5 |
| Production execution (batch creation) | 4.7 |
| Current inventory inquiries | 4.6 |
| Batch history & traceability APIs | 4.8 |
| Publish on **both** transitions | 5.2, 5.4 |
| Separate consumer doing real work | 5.5, 5.6 |
| Compose: app + MySQL + RabbitMQ + worker | 0.5 |
| Single-command startup | 0.6, 8.6 |
| React admin interface | Phase 7 |
| Migrations **and** seeders | 1.3–1.15 |
| README: setup + API usage | 8.1–8.4 |
| Public GitHub repo | 8.7 |

---

## Phase 0 — Foundation & Docker

- [x] 0.1 `.gitignore`, `.editorconfig`, `.gitattributes` as project files
- [x] 0.2 `composer create-project laravel/laravel backend` — Laravel 12.64, Sanctum, php-amqplib; Sail + Vite/npm stripped (API-only)
- [x] 0.3 `backend/Dockerfile` — Apache + mod_php 8.3 + `pdo_mysql`, `bcmath`, `sockets`, `pcntl`, `opcache`, composer; php.ini and vhost inlined
- [x] 0.4 `backend/.dockerignore` — keeps `vendor/` out of the build context
- [x] 0.5 `docker-compose.yml` — `app`, `mysql`, `rabbitmq`, `worker`, `frontend`; healthchecks + `depends_on: condition: service_healthy`
- [x] 0.6 `backend/docker-entrypoint.sh` — role-aware (`app` / `worker`), waits for MySQL & RabbitMQ, installs deps, `key:generate`, `migrate --seed`, readiness marker
- [x] 0.7 `.env.example` with every var documented (DB, RabbitMQ, `APP_URL`, CORS origin)
- [x] 0.8 `config/cors.php` — allow the frontend origin
- [x] 0.9 Pint (`pint.json`, strict types) + Larastan level 6 (`phpstan.neon`, no baseline) + `composer check`
- [x] 0.10 Verify `docker compose up -d` boots clean from zero — Apache → MySQL confirmed via `/` and `/up`, container reports healthy

## Phase 1 — Data Model

- [ ] 1.1 ERD in Mermaid, for the README
- [x] 1.2 Backed enums — `ItemType`, `ProductionStage`, `ProductionOrderStatus`, `MovementType`, `BatchOrigin`
- [x] 1.3 Migration `units` — code, name
- [x] 1.4 Migration `items` — sku (unique), name, `type`, unit_id, reorder_level, is_active, softDeletes
- [x] 1.5 Migration `bill_of_materials` — output_item_id, input_item_id, quantity_per_unit, unique(output, input)
- [x] 1.6 Migration `production_orders` — order_number (unique), output_item_id, stage, planned_quantity, produced_quantity, status, completed_at, failure_reason, created_by
- [x] 1.7 Migration `batches` — batch_number (unique), item_id, quantity_produced, quantity_remaining, produced_at, origin, production_order_id (nullable)
- [x] 1.8 Migration `production_consumptions` — production_order_id, input_batch_id, quantity_consumed  ← **the traceability edge**
- [x] 1.9 Migration `inventory_movements` — item_id, batch_id, type, signed quantity, balance_after, `reference_type`/`reference_id` (append-only ledger, audit source of truth)
- [x] 1.10 Migration `item_stocks` — item_id PK, quantity_on_hand (read cache, written in the same transaction)
- [x] 1.11 Migration `production_event_logs` — event_id (unique → idempotency), event_type, payload, processed_at
- [x] 1.12 FK constraints + composite index `(item_id, quantity_remaining)` for FIFO allocation
- [x] 1.13 Models — relations, casts, enum casts, no query logic
- [x] 1.14 Factories for every model
- [x] 1.15 Seeders — `AdminUserSeeder`, `UnitSeeder`, `ItemSeeder`, `BillOfMaterialSeeder` *(steel domain)*
- [ ] 1.16 `DemoProductionSeeder` — a complete two-stage traceable chain, visible on first boot. **Deferred to Phase 3**: it executes real runs through `ProductionService`, so it needs the services to exist first

## Phase 2 — Repository Layer

- [x] 2.0 ~~Typed filter DTOs~~ — **removed by decision.** Listing criteria are explicit typed parameters called with named arguments; the sortable-column whitelist lives in `ItemRepository`
- [x] 2.1 `Contracts/ItemRepositoryInterface` — type-scoped reads, `lowStock()`
- [x] 2.2 `Contracts/BatchRepositoryInterface` — FIFO lock, available quantity, decrement
- [x] 2.3 `Contracts/ProductionOrderRepositoryInterface` — order lock, consumption edges in both directions
- [x] 2.4 `Contracts/InventoryRepositoryInterface` — owns `item_stocks` **and** `inventory_movements`, because they are one unit of consistency
- [x] 2.4b `Contracts/BillOfMaterialRepositoryInterface` — recipe lookups, kept separate per ISP
- [x] 2.5 Eloquent implementations + generic `BaseRepository<TModel>` (shared CRUD only)
- [x] 2.6 `BatchRepository::lockAvailableFifo()` — `FOR UPDATE`, ordered by `produced_at` with id tiebreak ← concurrency guard
- [x] 2.7 `ProductionOrderRepository::consumptionsWithBatches()` / `consumptionsOfBatch()` — trace primitives, eager loaded (the recursion itself lives in `TraceabilityService`, 3.9)
- [x] 2.8 `RepositoryServiceProvider` singleton bindings, registered in `bootstrap/providers.php`
- [x] 2.9 Verified against MySQL — bindings resolve, FIFO ordering correct, ledger sum equals cached balance after receipt and consumption, `lowStock` includes items with no stock row

## Phase 3 — Service Layer

- [x] 3.1 ~~DTOs~~ — **removed by decision**; services take typed method parameters instead
- [x] 3.2 `ItemService` — CRUD per type; refuses to delete an item holding stock (`ItemHasStockException`)
- [x] 3.3 `InventoryService` — `receive()` (purchase batch + movement + stock bump via `BatchFactory`), `snapshot()`, `stockFor()`, `movementsFor()`
- [x] 3.4 `BatchNumberGeneratorInterface` + `BatchNumberGenerator` — `RM-20260730-0001`, `SF-…`, `FG-…`, scoped per (date, item type). `OrderNumberGeneratorInterface` + impl alongside it — `PO-20260730-0001`
- [x] 3.4b `BatchFactory` — creates a batch with a guaranteed-unique number, retrying on a unique-constraint collision (two concurrent receipts of *different* items of the same type can compute the same candidate number). Shared by `InventoryService` and `ProductionService` so the retry logic exists once
- [x] 3.5 `InventoryAllocator` — FIFO plan across locked batches; throws `InsufficientInventoryException` **before any write**
- [x] 3.6 ~~`ProductionStageResolver` + strategy classes~~ — **consolidated into the `ProductionStage` enum** (`app/Enums/ProductionStage.php`, already built in Phase 1). The enum's `inputType()`/`outputType()`/`forOutputType()` already are the Open/Closed extension point: a third stage is a new enum case, zero service changes. A separate Strategy class hierarchy over a fixed 2-case enum would duplicate that without adding real behaviour — dropped for the same reason DTOs were
- [x] 3.7 `ProductionService::createOrder()` — resolves stage from output item, requires a BOM to exist
- [x] 3.8 `ProductionService::execute()` — one `DB::transaction(..., attempts: 3)`: lock order → resolve BOM → allocate + lock inputs (FIFO) → decrement batches → write consumption rows → create output batch → bump output stock → write movements → complete order. Retry-on-deadlock via Laravel's transaction attempts, not manual multi-item lock ordering — see code comment for why. `ProductionCompleted` dispatch deferred to Phase 5
- [x] 3.9 `TraceabilityService::traceUpstream()` / `traceDownstream()` — recursive, depth-guarded (`MAX_DEPTH = 10` against a structural max of 2)
- [x] 3.10 Domain exceptions — `InsufficientInventoryException` (422), `InvalidProductionStageException` (422), `ProductionOrderNotPendingException` (409), `ItemHasStockException` (409). Each is self-rendering (`render(Request): JsonResponse` on the exception class, Laravel 11+ idiom) — no central Handler match-statement to keep in sync
- [x] 3.11 `DemoProductionSeeder` (task 1.16, deferred from Phase 1) — runs a real two-stage chain through `InventoryService`/`ProductionService`, not hand-inserted rows, so a broken service would show up as a seeding failure, not silently pass
- [x] 3.12 Verified against MySQL via `migrate --seed` + a service-layer smoke script: seeded stock matches hand-computed BOM arithmetic exactly; ledger sum equals cached balance for every item; a full finished→semi→raw trace returns the correct nested tree with quantities at each level; an over-sized order is rejected with rod stock and order status provably unchanged (no partial write); a repeated `execute()` on a completed order is rejected as 409

## Phase 4 — REST API

- [x] 4.1 FormRequests — `Store`/`Update` × `RawMaterial`, `SemiFinishedProduct`, `FinishedProduct` (thin subclasses of shared `StoreItemRequest`/`UpdateItemRequest`, since the field shape is identical and only the stage differs); plus `IndexItemRequest`, `IndexBatchRequest`, `IndexProductionOrderRequest`, `ReceiveStockRequest`, `StoreProductionOrderRequest`, `LoginRequest`. No `ExecuteProductionRequest` — that endpoint takes no body, only a route id. No `authorize()` overrides: `auth:sanctum` gates every write and there are no per-role rules, so FormRequest's default (`true` when absent) is the honest expression of that
- [x] 4.2 Resources — `ItemResource`, `ItemSummaryResource` (compact shape for nesting), `UnitResource`, `BatchResource`, `ProductionOrderResource`, `ProductionConsumptionResource`, `InventoryStockResource`, `InventoryMovementResource`. No `TraceabilityNodeResource` — the trace tree is a computed nested array from `TraceabilityService`, not an Eloquent model, so it goes through `ApiResponse::data()` instead
- [x] 4.3 `ApiResponse` helper + `ForceJsonResponse` middleware (guarantees JSON errors even without an `Accept` header)
- [x] 4.4 Auth — `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`; Sanctum personal access tokens (not the cookie/stateful flow, which is for same-domain SPAs)
- [x] 4.5 `RawMaterialController`, `SemiFinishedProductController`, `FinishedProductController` — full CRUD over a shared abstract `ItemController`. Each concrete class keeps one-line actions because Laravel resolves a FormRequest by the exact class in the method signature, so the Store/Update actions cannot be defined once in the base
- [x] 4.6 `InventoryController` — `GET /inventory`, `GET /inventory/stage/{stage}`, `GET /inventory/low-stock`, `POST /inventory/receipts`
- [x] 4.7 `ProductionOrderController` — `index`, `store`, `show`, `POST /{order}/execute`
- [x] 4.8 `BatchController` — `index`, `show`, `GET /{batch}/trace`, plus `GET /{batch}/trace-downstream` (the reverse question; the repository query already existed)
- [x] 4.9 `GET /items/{item}/movements`
- [x] 4.10 Pagination / filter / sort conventions + `throttle:api` (120/min, keyed by user then IP)
- [x] 4.11 `routes/api.php` under `v1` — **explicit routes, not `Route::apiResource()`**: Laravel matches a plain route parameter to a controller argument by *name*, and apiResource would generate `{raw_material}` which cannot bind to `$rawMaterial`. Explicit routes also accommodate the non-CRUD actions (execute, trace, receipts)
- [x] 4.12 `BatchService` — batch listing/show, kept separate from `TraceabilityService` so "how batches are fetched" and "how the consumption graph is walked" have one reason to change each
- [x] 4.13 Verified over real HTTP — **58/58 checks pass**: token auth incl. 401 on missing/expired and after logout; cross-type isolation (a semi-finished id 404s on the raw-materials route); full CRUD; delete guarded by 409 when stock exists; filter/sort/pagination incl. 422 on a non-whitelisted `sort_by` and `per_page=500`; receipts moving stock 1385→1885 with a new batch; execute completing with the correct output batch and consumption count; **409 on double-execute**; **422 on insufficient stock with the shortfall reported and stock provably unchanged and the order left pending**; and the trace returning finished→semi→raw with correct quantities at each level

## Phase 5 — RabbitMQ

- [x] 5.1 `MessagePublisherInterface` + `RabbitMqPublisher` (php-amqplib) — persistent delivery mode, publisher confirms (`confirm_select` + `wait_for_pending_acks`, so publish only returns once the broker accepted the message)
- [x] 5.2 Idempotent topology in code (`RabbitMqConnector`) — topic exchange `production.events`, durable queue bound on `production.*.completed`, DLX `production.events.dlx` → `production.events.dlq`. Shared by publisher and consumer so both agree on definitions; verified with `rabbitmqctl list_exchanges/list_bindings`
- [x] 5.3 Versioned envelope (`EventEnvelope`) — `event_id`, `event_type`, `version`, `routing_key`, `occurred_at`, `payload`, plus a `validate()` used by the consumer to send malformed messages straight to the DLQ
- [x] 5.4 `ProductionCompleted` implements `ShouldDispatchAfterCommit` — the dispatch is deferred until the transaction commits, so a deadlock-retried run can't emit a phantom event
- [x] 5.5 `php artisan rabbitmq:consume` — manual ack, prefetch/QoS, bounded retry via an `x-attempts` header with republish, poison → DLQ, graceful SIGTERM/SIGINT shutdown via `pcntl`
- [x] 5.6 `ProductionEventProcessor` — writes `production_event_logs`, logs the event, sends a notification. **Does not** touch inventory: that stays synchronous (see decisions table)
- [x] 5.7 Idempotency via the unique index on `event_id` — a redelivered message is caught as a `UniqueConstraintViolationException` and acked as already-handled, which is what makes at-least-once delivery safe
- [x] 5.8 Wired as the `worker` container CMD; compose env moved into a YAML anchor so a developer's local `.env` can never change container wiring
- [x] 5.9 `NullPublisher` and `LogPublisher` — `MESSAGE_PUBLISHER=null` lets the whole app run with no broker at all
- [x] 5.10 Verified end to end — **13/13 async checks**: execute publishes exactly 1 message while the event log stays empty (proving the work is genuinely deferred, not synchronous); a separate consumer process drains it and writes the row; a duplicate `event_id` is acked without a second row; a malformed message goes to the DLQ instead of looping. Then re-verified **in Docker**: the `worker` container consumed events automatically with no manual step, and graceful SIGTERM exits 0
- [x] 5.11 **Bug found and fixed** — the listener was registered twice (my explicit `Event::listen` *plus* Laravel 11+ auto-discovery of `app/Listeners`), so every production event published to RabbitMQ twice. Caught because the queue depth was 2 when it should have been 1. Explicit registration removed; verify wiring with `php artisan event:list`

## Phase 6 — Requirement Verification  *(replaces the original "Tests" phase)*

Automated PHPUnit tests are **not** a deliverable here — they aren't in the
assignment's evaluation criteria. Verification instead means driving the real
HTTP API and confirming each assignment requirement observably holds. Already
done for Phases 1–4 (58/58 checks); these are the ones still outstanding.

- [x] 6.1 CRUD happy paths + validation failures per resource — covered
- [x] 6.2 Production execution end to end, stock asserted before/after — covered
- [x] 6.3 Production rejected on insufficient stock, **no partial write, order left pending** — covered
- [x] 6.4 FIFO allocation order + stage legality (raw item rejected as output) — covered
- [x] 6.5 Traceability tree across both stages, terminating at a purchase leaf — covered
- [x] 6.6 Concurrency — **13/13 checks** against the Dockerised API, which is genuinely parallel because Apache runs `mpm_prefork` (the host's `php artisan serve` is single-threaded and could not have produced real contention). Two scenarios:
  - **Two orders competing for the same stock** (each needing 22.5 of 27.5 rods): exactly one 200 and one 422, stock deducted exactly once, rejected order left `pending`, no negative stock or batch remainder anywhere, ledger sum equals cached balance for every item
  - **The same order executed twice simultaneously**: exactly one 200 and one 409, one output batch, one consumption row, stock deducted once — `lockById()` doing its job
- [x] 6.6b **Bug found and fixed** — the insufficient-stock message reported a stale figure ("required 22.5, available 27.5" on the request that had just *lost* the race). Cause: `availableQuantity()` was a **non-locking** `SELECT SUM`, and under InnoDB's default REPEATABLE READ a non-locking read returns the snapshot from transaction start, while the `FOR UPDATE` read returns the latest committed data. `InventoryAllocator` now totals the rows it already locked, so the figure is correct by construction and one query cheaper. `availableQuantity()` became dead code and was removed from the interface and implementation
- [x] 6.7 RabbitMQ path — event published on completion, consumer writes `production_event_logs`, redelivery is idempotent, malformed → DLQ — covered (13/13)
- [x] 6.8 Re-verified against the **MySQL 8 container** — 58/58 API checks and the full async path both pass in Docker. Still worth repeating at 8.6 after any further schema change, since local MariaDB and MySQL 8 already diverged once on `timestamp` defaults

## Phase 7 — React Frontend  *(not evaluated — keep minimal)*

**Deliberately plain JavaScript** — no TypeScript, no Tailwind, no axios, no React
Query. Dependencies are react, react-dom, react-router-dom and vite. Hand-written
CSS and a ~50-line `fetch` wrapper cover everything this UI needs; a build
toolchain for an unevaluated admin screen would be cost without return.

- [x] 7.1 Vite + React (JSX, no TS) + hand-written CSS; `src/api.js` = fetch wrapper with bearer token + auto-logout on 401
- [x] 7.2 Login page; token validated against `/auth/me` on boot rather than trusted from localStorage
- [x] 7.3 App shell — persistent left sidebar grouped **Inventory / Production / Traceability**, top bar, active-route highlighting, live low-stock badge, responsive at 800px
- [x] 7.4 Dashboard — stat tiles per stage + full inventory table per stage, low-stock highlighted
- [x] 7.5 Raw Material / Semi-Finished / Finished CRUD — one `Items.jsx` serves all three (same reason one controller backs all three resources), with create/edit modal and validation errors mapped per field
- [x] 7.6 Receive stock form — raw materials only, shows current stock and recent receipts
- [x] 7.7 Production orders — history with stage/status filters, create modal, execute button; surfaces the 422 shortfall and the 409 conflict verbatim
- [x] 7.8 Batch list with stage/origin/in-stock filters + **recursive traceability tree** (upstream and downstream views)
- [x] 7.9 Event log view — rows written by the worker, auto-refreshing, with a "how to read this" note explaining the async path
- [x] 7.10 Small backend addition to support 7.9: `GET /production-events`, `ProductionEventLogResource`, `ProductionService::eventLog()` (queries the model directly — the documented carve-out for append-only tables)
- [x] 7.11 Verified in a real browser (Playwright driving Chrome) — **22/22 checks**: login, CRUD create/delete, the 409 delete guard, stock receipt, production create + execute, the insufficient-stock rejection, batch filtering, the finished→semi→raw trace tree terminating at a purchase leaf, the worker-populated event log, plus no JS exceptions and no unexpected 404s
- [x] 7.12 **Bug found and fixed** — on the production page the rejection banner never appeared: `execute()` set the error, then called `load()` whose first line was `setError('')`, wiping it before render. Found by instrumenting the browser (request fired, 422 returned, no banner). `load()` no longer clears state it doesn't own; a comment marks why
- [x] 7.13 **Requirement bug found and fixed** — `GET /inventory` was driven by `item_stocks`, and that row is only created on an item's *first* movement. So any item sitting at zero was **absent from the inventory view entirely**: the seeded Welding Rod (0 on hand) never appeared, and a newly created item didn't either. That directly undercuts "view current inventory at every production stage", since zero is the level that most needs to be seen. `InventoryRepository::snapshot()` is now driven by `items` with the stock relation left-joined and missing read as zero; `InventoryStockResource` wraps an `Item` accordingly, and the JSON shape is unchanged. Dashboard went from 6 rows to the correct 7

## Phase 8 — Docs & Submission

- [ ] 8.1 README — overview, architecture + layering diagram, ERD, one-command quickstart
- [ ] 8.2 README — full endpoint table with curl examples (login first)
- [ ] 8.3 README — RabbitMQ topology diagram + event catalog
- [ ] 8.4 README — design decisions & trade-offs, incl. the sync-inventory rationale ← interview crib sheet
- [ ] 8.5 Postman collection + `api.http` committed
- [ ] 8.6 Clean-room verify — `docker compose down -v && docker compose up -d` → seeded, API live, worker consuming
- [ ] 8.7 Walk the reviewer smoke test below, then hand off for publishing *(git/push by user)*

---

## 5. Reviewer Smoke Test

What a reviewer most likely does in their first fifteen minutes. Walk this
personally before submitting.

1. Clone → `docker compose up -d` → does it work with no extra steps?
2. Hit `GET /batches/{finished_batch}/trace` — is there seeded data, and does the tree reach raw materials?
3. Try producing more than available stock — is it actually rejected, with nothing written?
4. Open the RabbitMQ UI on :15672 — is there a real exchange, queue, and message flow?
5. Open three source files at random — is the layering real or decorative?
6. Read the README — is there a *why* anywhere, or only endpoint lists?

## 6. Known Failure Modes (guarded against)

| Common mistake | Guard |
|---|---|
| Traceability only one level deep | Recursive walk + test 6.5 |
| Single `quantity` column per product → batch tracing impossible | Per-batch `quantity_remaining` + FIFO |
| RabbitMQ as bare `dispatch()`, no visible AMQP | Explicit exchange / bindings / DLQ (5.2) |
| Float quantities → rounding drift | `decimal(15,4)` |
| Stock check outside the transaction → overselling | `FOR UPDATE` + test 6.6 |
| Publishing inside an open transaction → phantom events | `afterCommit` listener (5.4) |
| README lists endpoints, explains nothing | 8.4 design rationale |
| Seeders absent → reviewer sees an empty system | `DemoProductionSeeder` (1.15) |
