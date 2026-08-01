import { useCallback, useEffect, useState } from 'react'
import { api, fieldErrors } from '../api.js'
import { Alert, Confirm, Empty, Field, Loading, Modal, Panel, Pill, qty, when } from '../components/ui.jsx'

const EMPTY_FORM = { sku: '', name: '', unit_id: '', reorder_level: '0', description: '', is_active: true }

/**
 * One component serves all three item stages — the fields and behaviour are
 * identical, only the endpoint and label differ. Which is the same reason the
 * backend backs three REST resources with one shared controller.
 */
export default function Items({ endpoint, title, noun }) {
  const [rows, setRows] = useState(null)
  const [units, setUnits] = useState([])
  const [search, setSearch] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const [editing, setEditing] = useState(null) // null | {} for create | row for edit
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [confirming, setConfirming] = useState(null) // the row awaiting delete confirmation
  const [deleting, setDeleting] = useState(false)

  const load = useCallback(async () => {
    setError('')
    try {
      const query = search ? `?search=${encodeURIComponent(search)}&per_page=100` : '?per_page=100'
      const res = await api.get(`${endpoint}${query}`)
      setRows(res.data)
    } catch (err) {
      setError(err.message)
    }
  }, [endpoint, search])

  useEffect(() => {
    setRows(null)
    load()
  }, [load])

  // Units come from the raw-materials listing's embedded unit objects; there is
  // no dedicated units endpoint, and adding one just for a dropdown would be
  // scope the assignment never asked for.
  useEffect(() => {
    api
      .get('/raw-materials?per_page=100')
      .then((res) => {
        const seen = new Map()
        res.data.forEach((r) => r.unit && seen.set(r.unit.id, r.unit))
        setUnits([...seen.values()])
      })
      .catch(() => {})
  }, [])

  function openCreate() {
    setForm({ ...EMPTY_FORM, unit_id: units[0]?.id ?? '' })
    setFormErrors({})
    setEditing({})
  }

  function openEdit(row) {
    setForm({
      sku: row.sku,
      name: row.name,
      unit_id: row.unit?.id ?? '',
      reorder_level: qty(row.reorder_level),
      description: row.description ?? '',
      is_active: row.is_active,
    })
    setFormErrors({})
    setEditing(row)
  }

  async function save() {
    setSaving(true)
    setFormErrors({})
    try {
      const payload = {
        sku: form.sku,
        name: form.name,
        unit_id: Number(form.unit_id),
        reorder_level: form.reorder_level === '' ? 0 : Number(form.reorder_level),
        description: form.description || null,
        is_active: form.is_active,
      }
      if (editing.id) await api.put(`${endpoint}/${editing.id}`, payload)
      else await api.post(endpoint, payload)

      setEditing(null)
      setNotice(`${noun} ${editing.id ? 'updated' : 'created'}.`)
      load()
    } catch (err) {
      setFormErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setFormErrors({ _: err.message })
    } finally {
      setSaving(false)
    }
  }

  async function remove() {
    const row = confirming
    setError('')
    setNotice('')
    setDeleting(true)
    try {
      await api.del(`${endpoint}/${row.id}`)
      setConfirming(null)
      setNotice(`${noun} deleted.`)
      load()
    } catch (err) {
      // A 409 here is the domain guard refusing to delete an item that still
      // holds inventory — worth surfacing verbatim. Close the dialog so the
      // banner underneath is actually readable.
      setConfirming(null)
      setError(err.message)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <>
      <Alert error={error} success={notice} onClearSuccess={() => setNotice('')} />

      <Panel
        title={title}
        actions={
          <>
            <input
              placeholder="Search SKU or name…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{ width: 220 }}
            />
            <button className="primary" onClick={openCreate}>+ New</button>
          </>
        }
      >
        {rows === null ? (
          <Loading />
        ) : rows.length === 0 ? (
          <Empty>No {noun.toLowerCase()}s found.</Empty>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Name</th>
                  <th>Type</th>
                  <th className="num">On hand</th>
                  <th className="num">Reorder</th>
                  <th>Unit</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id}>
                    <td className="mono">{row.sku}</td>
                    <td>
                      {row.name}
                      {!row.is_active && <span className="retired">retired</span>}
                    </td>
                    <td><Pill value={row.type} /></td>
                    <td className={`num ${row.is_low_stock ? 'low' : ''}`}>{qty(row.quantity_on_hand)}</td>
                    <td className="num muted">{qty(row.reorder_level)}</td>
                    <td className="muted">{row.unit?.code}</td>
                    <td className="muted">{when(row.created_at)}</td>
                    <td style={{ textAlign: 'right' }}>
                      <button className="sm" onClick={() => openEdit(row)}>Edit</button>{' '}
                      {/* Always clickable: the dialog is where the user finds
                          out *why* an item cannot be removed, which a greyed-out
                          button here would never tell them. */}
                      <button className="sm danger" onClick={() => setConfirming(row)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {editing && (
        <Modal
          title={editing.id ? `Edit ${noun}` : `New ${noun}`}
          onClose={() => setEditing(null)}
          onSubmit={save}
          submitting={saving}
        >
          {formErrors._ && <Alert error={formErrors._} />}
          <Field label="SKU" error={formErrors.sku}>
            <input value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} required />
          </Field>
          <Field label="Name" error={formErrors.name}>
            <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </Field>
          <Field label="Unit" error={formErrors.unit_id}>
            <select value={form.unit_id} onChange={(e) => setForm({ ...form, unit_id: e.target.value })} required>
              <option value="">Select a unit…</option>
              {units.map((u) => (
                <option key={u.id} value={u.id}>{u.name} ({u.code})</option>
              ))}
            </select>
          </Field>
          <Field label="Reorder level" error={formErrors.reorder_level}>
            <input
              type="number"
              step="0.0001"
              min="0"
              value={form.reorder_level}
              onChange={(e) => setForm({ ...form, reorder_level: e.target.value })}
            />
          </Field>
          <Field label="Description" error={formErrors.description}>
            <input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </Field>
          {/* Retiring a product is the counterpart to deleting one: an item that
              has been used cannot be removed without breaking traceability, so
              this is how it leaves circulation. */}
          <Field label="Status" error={formErrors.is_active}>
            <label className="checkbox">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              />
              <span>Active</span>
            </label>
          </Field>
        </Modal>
      )}

      {confirming && (
        <Confirm
          title={confirming.can_delete ? `Delete ${noun.toLowerCase()}` : 'Cannot delete'}
          message={
            confirming.can_delete
              ? `Delete ${confirming.name} (${confirming.sku})?`
              : `${confirming.name} (${confirming.sku}) is still in use.`
          }
          detail={
            confirming.can_delete
              ? 'Nothing depends on this item — it has never been produced and no recipe refers to it.'
              : 'It holds stock, appears in production history, or a recipe names it — deleting it would break the traceability chain that already points at it. To take it out of circulation, press Edit and uncheck “Active”.'
          }
          confirmLabel="Delete"
          cancelLabel={confirming.can_delete ? 'Cancel' : 'Close'}
          disabled={!confirming.can_delete}
          busy={deleting}
          onConfirm={remove}
          onCancel={() => setConfirming(null)}
        />
      )}
    </>
  )
}
