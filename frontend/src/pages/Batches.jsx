import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api.js'
import { Alert, Empty, Loading, Panel, Pill, qty, when } from '../components/ui.jsx'

export default function Batches() {
  const [rows, setRows] = useState(null)
  const [filters, setFilters] = useState({ item_type: '', origin: '', available_only: false })
  const [search, setSearch] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    setRows(null)
    setError('')
    const params = new URLSearchParams({ per_page: '100' })
    if (filters.item_type) params.set('item_type', filters.item_type)
    if (filters.origin) params.set('origin', filters.origin)
    if (filters.available_only) params.set('available_only', '1')
    if (search) params.set('search', search)

    api
      .get(`/batches?${params}`)
      .then((res) => setRows(res.data))
      .catch((err) => setError(err.message))
  }, [filters.item_type, filters.origin, filters.available_only, search])

  return (
    <>
      <Alert error={error} />

      <Panel
        title="Batches"
        actions={
          <>
            <input
              placeholder="Search batch number…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{ width: 200 }}
            />
            <select
              value={filters.item_type}
              onChange={(e) => setFilters({ ...filters, item_type: e.target.value })}
              style={{ width: 160 }}
            >
              <option value="">All stages</option>
              <option value="raw">Raw material</option>
              <option value="semi_finished">Semi-finished</option>
              <option value="finished">Finished</option>
            </select>
            <select
              value={filters.origin}
              onChange={(e) => setFilters({ ...filters, origin: e.target.value })}
              style={{ width: 150 }}
            >
              <option value="">All origins</option>
              <option value="purchase">Purchased</option>
              <option value="production">Manufactured</option>
            </select>
            <label className="shrink muted" style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
              <input
                type="checkbox"
                checked={filters.available_only}
                onChange={(e) => setFilters({ ...filters, available_only: e.target.checked })}
                style={{ width: 'auto' }}
              />
              In stock only
            </label>
          </>
        }
      >
        {rows === null ? (
          <Loading />
        ) : rows.length === 0 ? (
          <Empty>No batches match this filter.</Empty>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Batch</th>
                  <th>Item</th>
                  <th>Stage</th>
                  <th className="num">Produced</th>
                  <th className="num">Remaining</th>
                  <th>Origin</th>
                  <th>From order</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((b) => (
                  <tr key={b.id}>
                    <td className="mono">{b.batch_number}</td>
                    <td>{b.item?.name}</td>
                    <td><Pill value={b.item?.type} /></td>
                    <td className="num">{qty(b.quantity_produced)}</td>
                    <td className="num">{qty(b.quantity_remaining)}</td>
                    <td><Pill value={b.origin} /></td>
                    <td className="mono muted">{b.production_order_number ?? '—'}</td>
                    <td className="muted">{when(b.produced_at)}</td>
                    <td style={{ textAlign: 'right' }}>
                      <Link to={`/trace/${b.id}`}>
                        <button className="sm">Trace</button>
                      </Link>
                    </td>
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
