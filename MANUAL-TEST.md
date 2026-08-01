# Manual Test Guide

A requirement-by-requirement walkthrough that can be performed entirely from the
admin UI, with the API and RabbitMQ management console used only to prove the
parts the UI cannot show (async delivery, concurrency).

Every step lists **what to do** and **what must happen**. If an expectation does
not hold, the system has a defect.

---

## 0. Start the stack

```bash
docker compose up -d
docker compose ps       # all five services up, mysql/rabbitmq/app healthy
```

| What | Where | Credentials |
|---|---|---|
| Admin UI | http://localhost:5173 | `admin@pms.test` / `password` |
| API | http://localhost:8000/api/v1 | Bearer token from `/auth/login` |
| RabbitMQ console | http://localhost:15672 | `pms_user` / `pms_secret` |
| MySQL | `localhost:3307` | `pms_user` / `pms_secret` |

Seeded catalogue and recipes:

| Output | Input | Qty per unit |
|---|---|---|
| Steel Rod (semi) | Cold Rolled Steel Sheet | 2.5 kg |
| Galvanised Steel Sheet (semi) | Cold Rolled Steel Sheet | 8.0 kg |
| Galvanised Steel Sheet (semi) | Zinc Ingot | 0.4 kg |
| Steel Pipe (finished) | Steel Rod | 1.5 pcs |
| Steel Support Frame (finished) | Steel Rod | 4.0 pcs |
| Steel Support Frame (finished) | Galvanised Steel Sheet | 2.0 sheet |

---

## 1. Sign in — authentication

**Do:** open http://localhost:5173, sign in with the credentials above.

**Expect:** the Dashboard loads. A wrong password shows an error and does not
sign you in.

---

## 2. Record the baseline

**Do:** on the Dashboard, write down the current quantity of **Cold Rolled Steel
Sheet**, **Steel Rod** and **Steel Pipe**.

**Expect:** three separate stage sections — raw, semi-finished, finished — each
with its own quantities. This is requirement 1d: *independent inventory tracking
at each stage*.

---

## 3. CRUD on all three product types — requirement 4a

Repeat this on **Raw Materials**, **Semi-Finished** and **Finished Goods**:

| Do | Expect |
|---|---|
| **+ New**, fill SKU / name / unit / reorder level, save | Row appears in the table |
| **Edit** the row, change the name, save | Table shows the new name |
| **Delete** the row you just created | Row disappears |
| Try **+ New** with a duplicate SKU | Field-level validation error, nothing saved |
| Try **Delete** on a seeded item that has stock or history | Rejected with a reason — history must not be destroyed |

---

## 4. Receive raw material — the only inbound path

**Do:** **Receive Stock** → Cold Rolled Steel Sheet → quantity `200` → Receive.

**Expect:**
- Success message naming a new batch number (`BATCH-…`).
- The new batch appears under *Recent receipts* with origin `purchase`, remaining
  = 200.
- Dashboard: steel sheet is now **baseline + 200**.
- The dropdown offers **only raw materials** — semi-finished and finished stock
  can never be received, only produced.

---

## 5. Production run: raw → semi-finished — requirements 2a, 2b, 5a

**Do:** **Production Orders** → **+ New order** → output *Steel Rod*, quantity
`40` → Create. The order appears as `pending`. Press **Execute**.

**Expect:**
- Creating the order changes **no** stock — only executing does.
- Success banner naming the produced batch and the number of input batches consumed.
- Order status → `completed`, produced quantity 40.
- Dashboard: steel sheet **down by 100** (40 × 2.5), Steel Rod **up by 40**.

---

## 6. Production run: semi-finished → finished

**Do:** **+ New order** → output *Steel Pipe*, quantity `20` → Create → **Execute**.

**Expect:**
- Steel Rod **down by 30** (20 × 1.5), Steel Pipe **up by 20**.
- A second output batch is created.

Both transitions now exist, which is what requirement 5 asks to be published.

---

## 7. Insufficient stock must block production — requirement 2c

**Do:** **+ New order** → output *Steel Support Frame*, quantity `9999` → Create
→ **Execute**.

**Expect:**
- Red banner: insufficient inventory, naming the item and the shortfall.
- Order stays `pending`, **not** `failed` halfway.
- Dashboard quantities are **completely unchanged** — no partial consumption.
  This is the important half of the test: the transaction rolled back.

---

## 8. Duplicate execution must be rejected

**Do:** find a `completed` order. Its **Execute** button is gone. Repeat the call
directly:

```bash
curl -i -X POST http://localhost:8000/api/v1/production-orders/<id>/execute \
  -H "Authorization: Bearer <token>"
```

**Expect:** `409 Conflict`, no second output batch, no stock movement.

---

## 9. Traceability — requirement 3

**Do:** **Batches** → find the Steel Pipe batch from step 6 → click its batch
number (or **Production Orders** → click the output batch link).

**Expect:** a tree that walks the full chain:

```
Steel Pipe batch
  └── Steel Rod batch      (consumed 30 pcs)
        └── Cold Rolled Steel Sheet batch   (consumed 100 kg)
```

Every level shows batch number, quantity consumed and timestamp. This answers
"which raw material batch ended up in this finished product".

---

## 10. Message publishing — requirement 5a

**Do:** **Event Log**.

**Expect:** one row per production run, with routing keys
`production.raw_to_semi.completed` and `production.semi_to_finished.completed`,
each carrying the order, the batches and the quantities.

---

## 11. Asynchronous processing — requirements 5b and 5c

This is the test that proves the consumer is genuinely decoupled rather than
called inline.

```bash
docker compose stop worker
```

**Do:** run another production order in the UI (e.g. 10 Steel Rod) and execute it.

**Expect while the worker is stopped:**
- The API responds **immediately with success** — stock moves, batch is created.
- **Event Log has no new row.** Nothing consumed the message.
- RabbitMQ console → *Queues* → the production events queue shows **Ready = 1**.

```bash
docker compose start worker
```

**Expect within a few seconds:**
- Queue depth drops back to 0.
- The Event Log row appears without any action in the UI.

That gap between the API responding and the event being processed is the proof
that publishing and consuming are separate processes.

---

## 12. Concurrency safety

Two orders competing for the same stock must not both succeed.

```bash
# Create two pending orders for more stock than exists in total, then:
curl -s -o a.json -w "%{http_code}\n" -X POST .../production-orders/<A>/execute -H "Authorization: Bearer <token>" &
curl -s -o b.json -w "%{http_code}\n" -X POST .../production-orders/<B>/execute -H "Authorization: Bearer <token>" &
wait
```

**Expect:** exactly one `200` and one `422`. Stock is deducted once. The rejected
order remains `pending`.

---

## 13. Low stock

**Do:** look at the sidebar badge next to *Raw Materials*.

**Expect:** it counts items at or below their reorder level, and it updates as you
navigate after receiving stock.

---

## 14. Restart safety

```bash
docker compose restart
```

**Expect:** after the stack comes back, all batches, orders, stock levels and
event log rows are still present — data lives in named volumes, not in the
containers.

---

## Coverage summary

| Requirement | Covered by |
|---|---|
| 1 — data model, batches, per-stage inventory | 2, 3, 9 |
| 2 — inventory deduction, addition, shortage block | 4, 5, 6, 7 |
| 3 — full traceability | 9 |
| 4 — CRUD, production execution, inventory inquiry, batch history | 3, 5, 6, 9 |
| 5 — publish on both transitions, async consumer | 10, 11 |
| 6 — single-command Docker Compose | 0, 14 |
| 7 — React admin UI | every UI step |
