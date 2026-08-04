import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, fieldErrors } from '../api.js'
import { Alert, Empty, Field, Loading, Modal, Panel, Pill, qty, when } from '../components/ui.jsx'

export default function Production() {
  const [orders, setOrders] = useState(null)
  const [producible, setProducible] = useState([])
  const [filters, setFilters] = useState({ stage: '', status: '' })
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [creating, setCreating] = useState(false)
  const [form, setForm] = useState({ output_item_id: '', planned_quantity: '' })
  const [formErrors, setFormErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const [executingId, setExecutingId] = useState(null)
  const [recipe, setRecipe] = useState([])

  async function load() {
    try {
      // The API is paginated; the demo dataset fits one page, so the UI asks
      // for a large per_page rather than rendering pager controls.
      const params = new URLSearchParams({ per_page: '50' })
      if (filters.stage) params.set('stage', filters.stage)
      if (filters.status) params.set('status', filters.status)
      const res = await api.get(`/production-orders?${params}`)
      setOrders(res.data)
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    setOrders(null)
    load()
  }, [filters.stage, filters.status])

  // only semi-finished and finished items can be produced; raw materials are
  // received, never manufactured. The API enforces this too — this just avoids
  // offering an option that would be rejected
  useEffect(() => {
    Promise.all([
      api.get('/semi-finished-products?per_page=100&is_active=1'),
      api.get('/finished-products?per_page=100&is_active=1'),
    ])
      .then(([semi, fin]) => setProducible([...semi.data, ...fin.data]))
      .catch(() => {})
  }, [])

  // read-only — recipes are seeded setup, not editable here
  useEffect(() => {
    if (!form.output_item_id) {
      setRecipe([])
      return
    }
    let stale = false
    api
      .get(`/items/${form.output_item_id}/recipe`)
      .then((res) => !stale && setRecipe(res.data))
      .catch(() => !stale && setRecipe([]))
    return () => {
      stale = true
    }
  }, [form.output_item_id])

  async function create() {
    setBusy(true)
    setFormErrors({})
    try {
      const res = await api.post('/production-orders', {
        output_item_id: Number(form.output_item_id),
        planned_quantity: form.planned_quantity,
      })
      setCreating(false)
      setForm({ output_item_id: '', planned_quantity: '' })
      setNotice(`Order ${res.data.order_number} created. Execute it to consume inputs.`)
      load()
    } catch (err) {
      const fe = fieldErrors(err)
      setFormErrors(Object.keys(fe).length ? fe : { _: err.message })
    } finally {
      setBusy(false)
    }
  }

  async function execute(order) {
    setExecutingId(order.id)
    setError('')
    setNotice('')
    try {
      const res = await api.post(`/production-orders/${order.id}/execute`)
      setNotice(
        `${res.data.order_number} completed. Produced batch ${res.data.output_batch?.batch_number} ` +
          `from ${res.data.consumptions?.length ?? 0} input batch(es).`,
      )
      load()
    } catch (err) {
      // the interesting failures land here: 422 insufficient inventory (the
      // shortfall is spelled out in the validation message) and 409 already
      // executed
      const first = Object.values(err.body?.errors ?? {})[0]?.[0]
      setError(first ?? err.message)
      load()
    } finally {
      setExecutingId(null)
    }
  }

  const selected = producible.find((p) => String(p.id) === String(form.output_item_id))

  return (
    <>
      <Alert error={error} success={notice} onClearSuccess={() => setNotice('')} />

      <Panel
        title="Production orders"
        actions={
          <>
            <select value={filters.stage} onChange={(e) => setFilters({ ...filters, stage: e.target.value })} style={{ width: 200 }}>
              <option value="">All stages</option>
              <option value="raw_to_semi_finished">Raw → Semi-finished</option>
              <option value="semi_finished_to_finished">Semi-finished → Finished</option>
            </select>
            <select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })} style={{ width: 140 }}>
              <option value="">All statuses</option>
              <option value="pending">Pending</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
            </select>
            <button className="primary" onClick={() => setCreating(true)}>+ New order</button>
          </>
        }
      >
        {orders === null ? (
          <Loading />
        ) : orders.length === 0 ? (
          <Empty>No production orders match this filter.</Empty>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Stage</th>
                  <th>Output</th>
                  <th className="num">Planned</th>
                  <th className="num">Produced</th>
                  <th>Status</th>
                  <th>Output batch</th>
                  <th>By</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {orders.map((o) => (
                  <tr key={o.id}>
                    <td className="mono">{o.order_number}</td>
                    <td className="muted">{o.stage === 'raw_to_semi_finished' ? 'Raw → Semi' : 'Semi → Finished'}</td>
                    <td>{o.output_item?.name}</td>
                    <td className="num">{qty(o.planned_quantity)}</td>
                    <td className="num">{qty(o.produced_quantity)}</td>
                    <td><Pill value={o.status} /></td>
                    <td className="mono">
                      {o.output_batch ? (
                        <Link to={`/trace/${o.output_batch.id}`}>{o.output_batch.batch_number}</Link>
                      ) : (
                        <span className="muted">—</span>
                      )}
                    </td>
                    <td className="muted">{o.created_by ?? '—'}</td>
                    <td className="muted">{when(o.created_at)}</td>
                    <td style={{ textAlign: 'right' }}>
                      {o.status === 'pending' && (
                        <button
                          className="sm primary"
                          disabled={executingId === o.id}
                          onClick={() => execute(o)}
                        >
                          {executingId === o.id ? 'Running…' : 'Execute'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {creating && (
        <Modal
          title="New production order"
          onClose={() => setCreating(false)}
          onSubmit={create}
          submitting={busy}
          submitLabel="Create"
        >
          {formErrors._ && <Alert error={formErrors._} />}
          <Field label="Output product" error={formErrors.output_item_id}>
            <select
              value={form.output_item_id}
              onChange={(e) => setForm({ ...form, output_item_id: e.target.value })}
              required
            >
              <option value="">Select a product…</option>
              {producible.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.type === 'finished' ? 'finished' : 'semi-finished'})
                </option>
              ))}
            </select>
          </Field>
          <Field label={`Quantity to produce${selected ? ` (${selected.unit?.code})` : ''}`} error={formErrors.planned_quantity}>
            <input
              type="number"
              step="0.0001"
              min="0.0001"
              value={form.planned_quantity}
              onChange={(e) => setForm({ ...form, planned_quantity: e.target.value })}
              required
            />
          </Field>
          {recipe.length > 0 && (
            <div className="recipe">
              <div className="recipe-title">
                {form.planned_quantity ? 'Will consume' : 'Recipe per unit'}
              </div>
              {recipe.map((line) => {
                const perUnit = Number(line.quantity_per_unit)
                const planned = Number(form.planned_quantity)
                const required = planned > 0 ? (perUnit * planned).toFixed(4) : null
                const short = required !== null && Number(required) > Number(line.quantity_on_hand)

                return (
                  <div key={line.input_item.id} className={`recipe-line ${short ? 'short' : ''}`}>
                    <span className="name">{line.input_item.name}</span>
                    {required === null ? (
                      <span className="need">
                        {qty(line.quantity_per_unit)} {line.input_item.unit} / unit
                      </span>
                    ) : (
                      <>
                        {/* The working, not just the answer — an operator should be
                            able to check the arithmetic without opening the seeder. */}
                        <span className="calc">
                          {qty(form.planned_quantity)} × {qty(line.quantity_per_unit)} =
                        </span>
                        <span className="need">
                          {qty(required)} {line.input_item.unit}
                        </span>
                      </>
                    )}
                    <span className="have">
                      have {qty(line.quantity_on_hand)}
                      {short && ' (short)'}
                    </span>
                  </div>
                )
              })}
            </div>
          )}

          <p className="muted" style={{ fontSize: 12, margin: 0 }}>
            Inputs are derived from the bill of materials. Creating the order does
            not touch inventory; stock moves only when you execute it.
          </p>
        </Modal>
      )}
    </>
  )
}
