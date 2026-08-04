# Production Management System

A backend for a steel fabrication plant. Raw materials come in, get turned into
semi-finished parts, and those become finished goods. Every batch can be traced
back to the exact raw material it came from.

Laravel 12 · MySQL 8 · RabbitMQ 3 · React · Docker Compose

---

## Screenshots

**Traceability.** One finished batch, traced back through the semi-finished
batch to the raw steel it started as.

![Traceability](docs/screenshots/traceability.png)


**Dashboard.** Each stage keeps its own inventory.

![Dashboard](docs/screenshots/dashboard.png)

**Production is blocked when stock is short.** The order is not created and no
stock moves.

![Insufficient stock](docs/screenshots/insufficient-stock.png)

**RabbitMQ.** A real topic exchange, a bound work queue, and a dead letter queue.

![RabbitMQ](docs/screenshots/rabbitmq.png)

---

## Quick start

You need Docker. Nothing else.

```bash
git clone <repo-url>
cd production-management-system
docker compose up -d
```

That one command builds the image, waits for MySQL and RabbitMQ, installs
dependencies, runs migrations and seeders, then starts the API, the queue
worker, and the React UI.

First boot takes a couple of minutes because of `composer install`. Watch it
with `docker compose logs -f app`.

| What | Where | Login |
|---|---|---|
| Admin UI | http://localhost:5173 | `admin@pms.test` / `password` |
| API | http://localhost:8001/api/v1 | bearer token from `/auth/login` |
| RabbitMQ console | http://localhost:15672 | `pms_user` / `pms_secret` |
| MySQL | `localhost:3307` | `pms_user` / `pms_secret` |

The seeder does not just insert rows. It runs a real two stage production chain
through the actual services, so a broken service shows up as a failed seed.

To start over: `docker compose down -v && docker compose up -d`

---

## Try it in two minutes

Import `postman_collection.json` into Postman. Run **Auth → Login** first. It
saves the token, so every other request is authenticated from then on.

Then open the **Walkthrough** folder and run it top to bottom. Each request
saves the ids the next one needs, so you never copy an id by hand.

| Step | Request | What you should see |
|---|---|---|
| 1 | Pick a raw material | 7 items across three stages, each with its own quantity |
| 2 | Receive stock | a purchase batch, numbered like `RM-20260803-0001` |
| 3 | Pick a semi finished product | the item the next order will produce |
| 4 | Create a production order | status `pending`, and **stock has not moved yet** |
| 5 | Execute it | now stock moves and an output batch appears |
| 6 | Trace the batch back | the full chain down to the raw material |
| 7 | Trace forward | everything made out of that batch |
| 8 | Check the event log | a row that RabbitMQ delivered a moment ago |

Two more requests sit in **Production orders**, and they are the interesting
ones:

- **Execute** the same order twice and the second call returns **409**. An
  order runs once, whatever else happens.
- **Execute (not enough stock)** returns **422** naming the item, the amount
  needed, and the shortfall. Check inventory afterwards and nothing moved.

The whole collection is safe to run twice. Ids are chained, SKUs are unique per
run, and the failure cases build their own data.

---

## What it does

| The assignment asks for | Where it lives |
|---|---|
| Raw → semi-finished → finished workflow | `ProductionService::execute()` |
| Independent inventory per stage | one `items` table with a `type`, one stock row per item |
| Batches with number, quantity, timestamp | `batches` table, numbers like `RM-20260803-0001` |
| Which raw materials went into each batch | `production_consumptions`, one row per input batch |
| Deduct inputs, add outputs | same transaction as the order, `ProductionService` |
| Block production when stock is short | `InventoryAllocator`, inside a row lock |
| Trace finished → semi → raw | `TraceabilityService`, walks the graph recursively |
| CRUD for all three product types | three REST resources, one shared controller |
| Current inventory | `GET /inventory`, `GET /inventory/stage/{stage}` |
| Batch history and traceability API | `GET /batches`, `/trace`, `/trace-downstream` |
| Publish on both transitions | one event, both stages, `ProductionCompleted` |
| A separate consumer doing real work | `worker` container running `queue:work rabbitmq` |
| Compose with app, MySQL, RabbitMQ, worker | `docker-compose.yml` |
| Migrations and seeders | `database/migrations`, `database/seeders` |
| React admin UI | `frontend/` |

---

## How it fits together

### The folders

```
app/Http/Requests/      what is allowed in (validation)
app/Http/Controllers/   translates HTTP to a service call, no rules here
app/Services/           all the business rules, all the transactions
app/Repositories/       all the queries, no SQL anywhere else
app/Models/             tables, relations, casts
app/Http/Resources/     what goes out (the JSON shape)
app/Enums/              fixed values and the rules attached to them
app/Jobs, app/Listeners the async path
```

### A simple request, start to finish

`GET /raw-materials?search=steel`

```
routes/api.php                    finds the route
  ItemFilterRequest               checks search, is_active, per_page
  RawMaterialController::index    says "I am the raw material resource"
    ItemService::list             passes the filters down
      ItemRepository              builds and runs the query
        ItemModel                 the items table
  ItemResource                    turns each row into JSON
```

Six files, each with one job. Read them in that order and the whole codebase
makes sense.

### The interesting request

`POST /production-orders/{id}/execute`

Start at `routes/api.php`, then follow this:

**1. `ProductionOrderController::execute()`**
Four lines. It finds the order, calls the service, returns a resource. No rules
live here.

**2. `ProductionService::execute()`**
Everything below runs inside one `DB::transaction`, so the order either
completes fully or leaves no trace.

**3. `lockPendingOrder()`**
Reads the order again with `SELECT ... FOR UPDATE` and checks it is still
pending. The caller's copy may be stale. This is what turns a second execute
call into a 409 instead of a second stock deduction.

**4. `allocateInputs()` → `InventoryAllocator`**
Works out which batches cover each input, oldest first (FIFO). Nothing is
written yet. If any input is short it throws here and the whole transaction
rolls back, so an order can never consume half its inputs.

**5. `consumeInputs()`**
Now it writes. For each batch it takes from: a consumption row (this is the
traceability edge), a smaller `quantity_remaining`, and a ledger row.

**6. `produceOutput()` → `BatchService::make()`**
Creates the output batch with a unique number, adds the quantity to stock,
writes the matching ledger row.

**7. `ProductionCompleted::dispatch()`**
Fires after the transaction commits, never inside it.

**8. `PublishProductionCompleted` → `RecordProductionCompleted`**
The listener queues a job. The job runs in the `worker` container, not in the
request. It writes the row you see at `GET /production-events`.

**9. `ProductionOrderResource`**
Shapes the JSON that goes back.

---

## API reference

Base URL: `http://localhost:8001/api/v1`

Every route except login needs a header:

```
Authorization: Bearer <token>
Accept: application/json
```

Every response is JSON wrapped in `data`. List endpoints that can grow add
`links` and `meta` for pagination:

```json
{
  "data": [ "..." ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 7, "last_page": 1 }
}
```

Quantities are always strings, never numbers. The reason is in
[Quantities are strings](#quantities-are-strings).

---

### Auth

#### POST `/auth/login`

The only public route.

| Field | Type | |
|---|---|---|
| `email` | string | required |
| `password` | string | required |

```json
{ "email": "admin@pms.test", "password": "password" }
```

**200**

```json
{
  "data": {
    "token": "2|ralY0PcpGGiD5qPXKhqdW7h81esPgVVC8wxfoTzk573d0428",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Plant Administrator", "email": "admin@pms.test" }
  }
}
```

Wrong credentials give **401**. Send that token as `Authorization: Bearer ...`
on every other call.

#### GET `/auth/me`

**200**

```json
{ "data": { "id": 1, "name": "Plant Administrator", "email": "admin@pms.test" } }
```

#### POST `/auth/logout`

Revokes the token that made the call. Other tokens for the same user keep
working.

**200**

```json
{ "message": "Logged out." }
```

---

### Products

Three item types, one shape. Everything below works the same for all three:

| Type | Prefix |
|---|---|
| Raw material | `/raw-materials` |
| Semi finished | `/semi-finished-products` |
| Finished | `/finished-products` |

The examples use `/raw-materials`.

#### GET `/raw-materials`

| Query | Type | |
|---|---|---|
| `search` | string | matches sku or name |
| `is_active` | boolean | |
| `per_page` | integer | 1 to 100, default 15 |

**200**, paginated

```json
{
  "data": [
    {
      "id": 1,
      "sku": "RAW-STEEL-SHEET",
      "name": "Cold Rolled Steel Sheet",
      "type": "raw",
      "unit": { "id": 1, "code": "kg", "name": "Kilogram" },
      "description": null,
      "reorder_level": "500.0000",
      "quantity_on_hand": "1385.0000",
      "is_low_stock": false,
      "is_active": true,
      "can_delete": false,
      "created_at": "2026-08-04T19:02:08+00:00",
      "updated_at": "2026-08-04T19:02:08+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 15, "total": 3, "last_page": 1 }
}
```

Each row already carries its stock and a low stock flag, so a list screen needs
no second call. `can_delete` is a hint for the UI, and the server checks it
again anyway.

#### POST `/raw-materials`

| Field | Type | |
|---|---|---|
| `sku` | string | required, unique, max 64 |
| `name` | string | required, max 255 |
| `unit_id` | integer | required, must exist |
| `description` | string | optional |
| `reorder_level` | numeric | optional, min 0 |
| `is_active` | boolean | optional, defaults to true |

```json
{ "sku": "RAW-NICKEL-INGOT", "name": "Nickel Ingot", "unit_id": 1, "reorder_level": "50" }
```

**201** returns the same shape as a list row, with `quantity_on_hand` at
`"0.0000"`.

A duplicate sku gives **422**:

```json
{
  "message": "The sku has already been taken.",
  "errors": { "sku": ["The sku has already been taken."] }
}
```

#### GET `/raw-materials/{id}`

**200**, one row in the shape above. Unknown id gives **404**.

#### PUT or PATCH `/raw-materials/{id}`

Same fields as create, all optional. PATCH updates only what you send.

```json
{ "reorder_level": "150" }
```

**200** returns the updated row.

#### DELETE `/raw-materials/{id}`

Soft delete. **204** with an empty body.

Refused with **409** when the item still has stock, batches, or appears in a
recipe, because removing it would break the trace history:

```json
{
  "message": "Cannot delete Cold Rolled Steel Sheet (RAW-STEEL-SHEET) while it still has inventory on hand."
}
```

#### GET `/items/{id}/recipe`

The bill of materials: what one unit of this item is made of. Raw materials
return an empty list, since nothing goes into them.

**200**

```json
{
  "data": [
    {
      "input_item": {
        "id": 1,
        "sku": "RAW-STEEL-SHEET",
        "name": "Cold Rolled Steel Sheet",
        "type": "raw",
        "unit": "kg"
      },
      "quantity_per_unit": "2.5000",
      "quantity_on_hand": "1385.0000"
    }
  ]
}
```

This is what execute reads to work out how much to consume.

---

### Inventory

#### GET `/inventory`

Every item across all three stages. Items that have never moved appear with
`"0.0000"` rather than going missing.

**200**, plain array

```json
{
  "data": [
    {
      "item": {
        "id": 1,
        "sku": "RAW-STEEL-SHEET",
        "name": "Cold Rolled Steel Sheet",
        "type": "raw",
        "unit": "kg"
      },
      "quantity_on_hand": "1385.0000",
      "reorder_level": "500.0000",
      "is_low_stock": false,
      "updated_at": "2026-08-04T19:02:12+00:00"
    }
  ]
}
```

#### GET `/inventory/stage/{stage}`

The same shape, one stage only. `{stage}` is `raw`, `semi_finished`, or
`finished`. Anything else gives **404**.

#### GET `/inventory/low-stock`

Items at or below their reorder level. This drives the dashboard alert panel.

| Query | |
|---|---|
| `type` | optional: `raw`, `semi_finished`, `finished` |

**200**, same shape as a product list row.

#### POST `/inventory/receipts`

Receive raw material. The only way raw stock goes up.

| Field | Type | |
|---|---|---|
| `item_id` | integer | required, must be a **raw material** |
| `quantity` | numeric | required, greater than 0 |
| `produced_at` | date | optional, sets the batch date used for FIFO |
| `note` | string | optional, max 500 |

```json
{ "item_id": 1, "quantity": "500", "note": "Delivery note 4471" }
```

**201**

```json
{
  "data": {
    "id": 8,
    "batch_number": "RM-20260804-0001",
    "item": {
      "id": 1,
      "sku": "RAW-STEEL-SHEET",
      "name": "Cold Rolled Steel Sheet",
      "type": "raw",
      "unit": "kg"
    },
    "quantity_produced": "500.0000",
    "quantity_remaining": "500.0000",
    "origin": "purchase",
    "production_order_number": null,
    "produced_at": "2026-08-04T19:32:48+00:00",
    "created_at": "2026-08-04T19:32:48+00:00"
  }
}
```

Pass a semi finished or finished item and you get **422**. Those are produced,
not bought.

#### GET `/items/{id}/movements`

The full ledger for one item. Every increase and decrease, with the reason.
Add the quantities up and you get the current stock, which is the audit trail
behind the number.

**200**, paginated, newest first

```json
{
  "data": [
    {
      "id": 5,
      "type": "production_input",
      "quantity": "-240.0000",
      "balance_after": "1385.0000",
      "batch_number": "RM-20260730-0001",
      "reference_type": "App\\Models\\ProductionOrderModel",
      "reference_id": 2,
      "note": null,
      "created_at": "2026-08-04T19:02:12+00:00"
    },
    {
      "id": 1,
      "type": "receipt",
      "quantity": "2000.0000",
      "balance_after": "2000.0000",
      "batch_number": "RM-20260730-0001",
      "reference_type": null,
      "reference_id": null,
      "note": null,
      "created_at": "2026-08-04T19:02:09+00:00"
    }
  ]
}
```

`type` is one of `receipt`, `production_input`, `production_output`.
`reference_type` and `reference_id` point at whatever caused the movement, so a
receipt has none and a production movement points at the order.

---

### Production

#### GET `/production-orders`

| Query | |
|---|---|
| `stage` | `raw_to_semi_finished` or `semi_finished_to_finished` |
| `status` | `pending`, `completed`, `failed`, `cancelled` |
| `per_page` | 1 to 100, default 15 |

**200**, paginated.

#### POST `/production-orders`

Plans a run. **Nothing moves in inventory yet.**

| Field | Type | |
|---|---|---|
| `output_item_id` | integer | required, must exist |
| `planned_quantity` | numeric | required, greater than 0 |

```json
{ "output_item_id": 4, "planned_quantity": "10" }
```

You never send the stage. It is worked out from the output item's type, so a
semi finished output means a raw to semi run. Ask for a raw material as output
and you get **422**, because raw materials are bought, not made.

**201**, with `status` at `"pending"` and `output_batch` still `null`.

#### GET `/production-orders/{id}`

**200**, the order with everything it touched.

```json
{
  "data": {
    "id": 1,
    "order_number": "PO-20260804-0001",
    "stage": "raw_to_semi_finished",
    "output_item": {
      "id": 4,
      "sku": "SEMI-STEEL-ROD",
      "name": "Steel Rod",
      "type": "semi_finished",
      "unit": "pcs"
    },
    "planned_quantity": "150.0000",
    "produced_quantity": "150.0000",
    "status": "completed",
    "failure_reason": null,
    "created_by": null,
    "output_batch": {
      "id": 3,
      "batch_number": "SF-20260804-0001",
      "quantity_produced": "150.0000",
      "quantity_remaining": "27.5000",
      "origin": "production",
      "production_order_number": "PO-20260804-0001",
      "produced_at": "2026-08-04T19:02:09+00:00"
    },
    "consumptions": [
      {
        "quantity_consumed": "375.0000",
        "input_batch": {
          "id": 1,
          "batch_number": "RM-20260730-0001",
          "item": {
            "id": 1,
            "sku": "RAW-STEEL-SHEET",
            "name": "Cold Rolled Steel Sheet",
            "type": "raw"
          }
        }
      }
    ]
  }
}
```

`consumptions` is the traceability record. One row per input batch actually
taken from, with the exact amount.

#### POST `/production-orders/{id}/execute`

No body. This is the call that moves stock.

In one transaction it locks the order, allocates input batches oldest first,
consumes them, creates the output batch, and writes the ledger rows. Then it
publishes an event after the commit.

**200** returns the completed order in the shape above.

**409** if the order is not pending, which is what stops the same order running
twice:

```json
{
  "message": "Production order PO-20260804-0006 cannot be modified because it is already completed."
}
```

**422** if any input is short. Nothing at all is written, so there is no half
consumed batch to clean up:

```json
{
  "message": "Insufficient inventory for Cold Rolled Steel Sheet (RAW-STEEL-SHEET): 2499997.5000 required, 1860.0000 available, short by 2498137.5000.",
  "errors": {
    "planned_quantity": [
      "Insufficient inventory for Cold Rolled Steel Sheet (RAW-STEEL-SHEET): 2499997.5000 required, 1860.0000 available, short by 2498137.5000."
    ]
  }
}
```

The message names the item, what was needed, what was there, and the shortfall.

#### GET `/production-events`

Read only. Every row here was written by the RabbitMQ worker, never by a web
request, so this is the simplest proof the async path runs.

| Query | |
|---|---|
| `per_page` | 1 to 100, default 15 |

**200**, paginated, newest first

```json
{
  "data": [
    {
      "id": 5,
      "event_id": "45681f48-f07b-4445-b8c8-5f777af9d85e",
      "event_type": "production.completed",
      "routing_key": "production.semi_to_finished.completed",
      "order_number": "PO-20260804-0005",
      "attempts": 1,
      "payload": {
        "stage": "semi_finished_to_finished",
        "output": {
          "item_sku": "FIN-STEEL-PIPE",
          "item_name": "Steel Pipe",
          "item_type": "finished",
          "batch_number": "FG-20260804-0003",
          "quantity": "15.0000"
        },
        "consumed": [
          {
            "item_sku": "SEMI-STEEL-ROD",
            "batch_number": "SF-20260804-0001",
            "quantity": "22.5000"
          }
        ],
        "order_number": "PO-20260804-0005",
        "production_order_id": 5,
        "completed_at": "2026-08-04T19:02:12+00:00"
      },
      "occurred_at": "2026-08-04T19:02:12+00:00",
      "processed_at": "2026-08-04T19:02:43+00:00",
      "lag_seconds": 31
    }
  ]
}
```

`occurred_at` is when the API published it, `processed_at` is when the worker
handled it, and `lag_seconds` is the gap. A non zero gap is the async pipeline
showing its work. `event_id` is the idempotency key.

---

### Batches and traceability

#### GET `/batches`

One table covers all three stages.

| Query | |
|---|---|
| `search` | batch number, sku, or item name |
| `item_type` | `raw`, `semi_finished`, `finished` |
| `origin` | `purchase` or `production` |
| `available_only` | `1` hides batches that are used up |
| `per_page` | 1 to 100, default 15 |

**200**, paginated.

#### GET `/batches/{id}`

**200**

```json
{
  "data": {
    "id": 1,
    "batch_number": "RM-20260730-0001",
    "item": {
      "id": 1,
      "sku": "RAW-STEEL-SHEET",
      "name": "Cold Rolled Steel Sheet",
      "type": "raw",
      "unit": "kg"
    },
    "quantity_produced": "2000.0000",
    "quantity_remaining": "1385.0000",
    "origin": "purchase",
    "production_order_number": null,
    "produced_at": "2026-07-30T19:02:09+00:00",
    "created_at": "2026-08-04T19:02:09+00:00"
  }
}
```

`origin` tells you whether the batch was bought or made. A `purchase` batch has
no production order.

#### GET `/batches/{id}/trace`

Upstream. Where did this come from?

**200**, a recursive tree

```json
{
  "data": {
    "batch_number": "FG-20260803-0003",
    "item": { "sku": "FIN-STEEL-PIPE", "name": "Steel Pipe", "type": "finished" },
    "quantity_produced": "15.0000",
    "production_order_number": "PO-20260803-0005",
    "consumed": [
      {
        "quantity_consumed": "22.5000",
        "batch": {
          "batch_number": "SF-20260803-0001",
          "item": { "sku": "SEMI-STEEL-ROD", "name": "Steel Rod" },
          "consumed": [
            {
              "quantity_consumed": "375.0000",
              "batch": {
                "batch_number": "RM-20260729-0001",
                "item": { "sku": "RAW-STEEL-SHEET" },
                "origin": "purchase"
              }
            }
          ]
        }
      }
    ]
  }
}
```

The tree stops at a `purchase` batch. It has no `consumed` key at all, because
that is where the material entered the plant and there is nothing further back
to find.

This is the recall question answered in one call.

#### GET `/batches/{id}/trace-downstream`

The other direction. Where did this end up?

Same tree shape, but the nesting key is `used_in` instead of `consumed`. Point
it at a bad raw material batch and you get every finished product affected,
which is the answer to a supplier recall.

---

### Errors

Every error is JSON with a `message`. Validation errors add a field keyed
`errors` object.

| Code | When | Body |
|---|---|---|
| 401 | bad or missing token | `{"message": "Unauthenticated."}` |
| 404 | no such record, or an unknown stage in the URL | `{"message": "No query results for model [App\\Models\\ItemModel] 9999"}` |
| 409 | the action is not allowed in the current state | order already ran, item still in use |
| 422 | validation failed, or not enough stock | `message` plus an `errors` map |

A 422 carries every failing field at once:

```json
{
  "message": "The sku has already been taken. (and 1 more error)",
  "errors": {
    "sku": ["The sku has already been taken."],
    "unit_id": ["The unit id field is required."]
  }
}
```

The split between 409 and 422 is deliberate. 422 means the request was wrong.
409 means the request was fine but the world has moved on.

---

## Data model

```
units ──< items ──< batches ──< production_consumptions >── production_orders
              │         │                                        │
              ├──< item_stocks                                    │
              ├──< inventory_movements >─────────────────────────┘
              └──< bill_of_materials (output_item_id, input_item_id)
```

| Table | What it holds |
|---|---|
| `units` | kg, ton, pcs, m, sheet |
| `items` | all three product types, told apart by `type` |
| `item_stocks` | cached quantity on hand, one row per item |
| `bill_of_materials` | recipes: this output needs that input, this much per unit |
| `production_orders` | a planned or completed run |
| `batches` | a lot of one item, with its own number and remaining quantity |
| `production_consumptions` | which batch an order took from, and how much |
| `inventory_movements` | append only ledger, every change with a signed quantity |
| `production_event_logs` | what the queue worker recorded |

### Why one items table

Raw, semi-finished and finished products have the same fields and the same
things hanging off them: batches, stock, movements, recipes. Three tables would
mean writing every relation three times, and the traceability walk would need
three different joins instead of one.

Each item still has its own stock row, so the stages never share a number.

### How traceability works

The chain is short:

```
output batch ──> its production order ──> its consumptions ──> input batches
```

Then repeat on each input batch. `TraceabilityService` walks this recursively
until it hits a `purchase` batch, which has no production order behind it.

The same edges read the other way answer "where did this raw material end up",
which is `/trace-downstream`.

### Quantities are strings

Every quantity is `decimal(15,4)` in MySQL and a string in PHP, and all the
arithmetic uses `bcmath`. Floats would drift, and stock that drifts is stock
you cannot trust.

---

## Async pipeline

When a production run finishes, the API publishes a message and returns. A
separate worker picks it up and does the follow-up work.

```
ProductionService::execute()  commits
        │
        ▼
ProductionCompleted (event, after commit)
        │
        ▼
PublishProductionCompleted (listener)  →  queues a job
        │
        ▼
   exchange  production.events          (topic)
        │  routing key: production.events.processing
        ▼
   queue     production.events.processing
        │
        ▼
worker container: php artisan queue:work rabbitmq
        │
        ▼
RecordProductionCompleted (job)  →  production_event_logs
```

A dead letter queue catches messages the worker gives up on:

```
production.events.dlx  ──(production.failed)──>  production.events.dlq
```

The topology is declared at startup in `docker-entrypoint.sh`, so a fresh
`docker compose up` always produces the same exchanges, queues and bindings.
You can see them in the RabbitMQ console at http://localhost:15672.

### Prove it is really async

```bash
docker compose stop worker
```

Now run **Walkthrough → Execute it** in Postman. It completes, stock moves, the
API returns 200. But **Check the event log** shows nothing new, and the
RabbitMQ console shows **Ready = 1** on `production.events.processing`.

```bash
docker compose start worker
```

The row appears within a second and the queue drops back to 0.

If the work were synchronous, the row would have been there all along.

### The job is idempotent

RabbitMQ delivers at least once, so the same message can arrive twice. Every
message carries a unique `event_id` with a unique index behind it. A repeat
delivery hits that index, gets treated as already handled, and does not write a
second row or send a second notification.

---

## Design decisions

**Stock moves synchronously, not in the worker.**
The brief lists "updating inventory" as something the consumer could do. Doing
that would break another requirement: "prevent production if inventory is
insufficient". Two orders sent at the same time would both pass the stock check
before either worker ran, and both would be accepted. So the check and the
deduction happen together, inside one transaction, behind a row lock. The
worker does the side effects: history, logging, notification.

**FIFO with a real database lock.**
`lockAvailableFifo()` selects the batches that still have stock, oldest first,
with `SELECT ... FOR UPDATE`. Two orders for the same item queue up at that
lock instead of both reading the same numbers. `DB::transaction(..., attempts:
3)` retries if InnoDB picks one as a deadlock victim.

**Batch and order numbers retry on collision.**
The number is a count plus one, which two requests in the same second can
compute identically. The unique index rejects the loser and the code takes the
next number. Five attempts, then it gives up.

**Repositories sit behind interfaces.**
Services only ever type-hint an interface. `RepositoryServiceProvider` is the
one place that says which class actually runs. That means a caching layer or an
in-memory fake for tests is a one line change and nothing else moves.

**Repositories are not used everywhere.**
`production_event_logs` is append only with no query complexity, so
`ProductionService::eventLog()` reads the model directly. A repository there
would be a file that earns nothing.

**Errors come from the service layer, not the controllers.**
No controller has a try/catch. Services throw `ValidationException` (422) or
`abort(409, ...)`, and Laravel turns those into responses. A refusal always
carries its real status code; nothing returns 200 with a failure inside.

**Models are named `ItemModel`, `BatchModel`, and so on.**
Because of that suffix Eloquent can no longer guess the table name or the
foreign keys, so both are declared explicitly on every model.

**Recipes are read only.**
They are part of the plant's setup and come from a seeder. The API exposes
`GET /items/{id}/recipe` so an operator can see what a run will consume before
committing to it.

**Deleting a used item is refused, not cascaded.**
If a batch was ever made from an item, or a recipe names it, deletion returns
409. Cascading would break a traceability chain that already exists. To take a
product out of circulation, clear `is_active`. Old stock of a retired item can
still be consumed, so a component gets phased out instead of stranded.

---

## Tooling

```bash
cd backend
php vendor/bin/phpstan analyse --memory-limit=1G   # static analysis
php vendor/bin/pint --test                         # code style
```

PHPStan runs at **level 6 with no baseline and no ignored errors**. Level 6
means every parameter and return type must be declared, including what is
inside an `array` or a `Collection`. That is why the docblocks carry things
like `Collection<int, BatchModel>`.

There is no automated test suite. The assignment's evaluation criteria do not
list tests, so the time went into requirement coverage and the Docker path
instead. Correctness was checked by driving the real HTTP API end to end.

---

## Project layout

```
production-management-system/
├── docker-compose.yml          app, mysql, rabbitmq, worker, frontend
├── postman_collection.json     every endpoint, ready to import
├── backend/
│   ├── app/                    see "How it fits together"
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/            DemoProductionSeeder runs a real chain
│   ├── docker-entrypoint.sh    waits for services, migrates, declares topology
│   ├── phpstan.neon            level 6
│   └── Dockerfile
└── frontend/                   React admin UI
```

The frontend is a thin admin interface. The assignment says it is not part of
the evaluation, so it stays small: no state library, no component kit, plain
CSS.
