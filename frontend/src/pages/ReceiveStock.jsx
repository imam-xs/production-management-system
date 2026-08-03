import { useEffect, useState } from 'react'
import { api, fieldErrors } from '../api.js'
import { Alert, Empty, Field, Loading, Panel, Pill, qty, when } from '../components/ui.jsx'

export default function ReceiveStock() {
  const [materials, setMaterials] = useState(null)
  const [recent, setRecent] = useState([])
  const [form, setForm] = useState({ item_id: '', quantity: '', note: '' })
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  async function load() {
    try {
      const [items, batches] = await Promise.all([
        // active only,a retired material must not be receivable either
        api.get('/raw-materials?per_page=100&is_active=1'),
        api.get('/batches?item_type=raw&origin=purchase&per_page=10'),
      ])
      setMaterials(items.data)
      setRecent(batches.data)
      setForm((f) => ({ ...f, item_id: f.item_id || items.data[0]?.id || '' }))
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function submit() {
    setBusy(true)
    setErrors({})
    setError('')
    setNotice('')
    try {
      const res = await api.post('/inventory/receipts', {
        item_id: Number(form.item_id),
        quantity: form.quantity,
        note: form.note || null,
      })
      setNotice(`Received into batch ${res.data.batch_number}.`)
      setForm({ ...form, quantity: '', note: '' })
      load()
    } catch (err) {
      const fe = fieldErrors(err)
      setErrors(fe)
      if (!Object.keys(fe).length) setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (materials === null) return <Loading />

  const selected = materials.find((m) => String(m.id) === String(form.item_id))

  return (
    <>
      <Alert error={error} success={notice} onClearSuccess={() => setNotice('')} />

      <Panel title="Receive raw material" padded>
        <form
          onSubmit={(e) => {
            e.preventDefault()
            submit()
          }}
        >
          <div className="row">
            <div style={{ flex: 2 }}>
              <Field label="Raw material" error={errors.item_id}>
                <select value={form.item_id} onChange={(e) => setForm({ ...form, item_id: e.target.value })} required>
                  {materials.map((m) => (
                    <option key={m.id} value={m.id}>{m.name} ({m.sku})</option>
                  ))}
                </select>
              </Field>
            </div>
            <div>
              <Field label={`Quantity${selected ? ` (${selected.unit?.code})` : ''}`} error={errors.quantity}>
                <input
                  type="number"
                  step="0.0001"
                  min="0.0001"
                  value={form.quantity}
                  onChange={(e) => setForm({ ...form, quantity: e.target.value })}
                  required
                />
              </Field>
            </div>
            <div style={{ flex: 2 }}>
              <Field label="Note (optional)" error={errors.note}>
                <input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
              </Field>
            </div>
            <div className="shrink">
              <div className="field">
                <label>&nbsp;</label>
                <button type="submit" className="primary" disabled={busy}>
                  {busy ? 'Receiving…' : 'Receive'}
                </button>
              </div>
            </div>
          </div>
          {selected && (
            <div className="muted" style={{ fontSize: 12 }}>
              Current stock: <strong>{qty(selected.quantity_on_hand)} {selected.unit?.code}</strong>
              {' · '}reorder level {qty(selected.reorder_level)}
            </div>
          )}
        </form>
      </Panel>

      <Panel title="Recent receipts">
        {recent.length === 0 ? (
          <Empty>No receipts yet.</Empty>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Batch</th>
                  <th>Material</th>
                  <th className="num">Received</th>
                  <th className="num">Remaining</th>
                  <th>Origin</th>
                  <th>Received at</th>
                </tr>
              </thead>
              <tbody>
                {recent.map((b) => (
                  <tr key={b.id}>
                    <td className="mono">{b.batch_number}</td>
                    <td>{b.item?.name}</td>
                    <td className="num">{qty(b.quantity_produced)}</td>
                    <td className="num">{qty(b.quantity_remaining)}</td>
                    <td><Pill value={b.origin} /></td>
                    <td className="muted">{when(b.produced_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </>
  )
}