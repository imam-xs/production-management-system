import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { Alert, Empty, Loading, Panel, Pill, qty } from '../components/ui.jsx'

const STAGES = [
  { key: 'raw', label: 'Raw Materials' },
  { key: 'semi_finished', label: 'Semi-Finished' },
  { key: 'finished', label: 'Finished Goods' },
]

export default function Dashboard() {
  const [inventory, setInventory] = useState(null)
  const [lowStock, setLowStock] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([api.get('/inventory'), api.get('/inventory/low-stock')])
      .then(([inv, low]) => {
        setInventory(inv.data)
        setLowStock(low.data)
      })
      .catch((err) => setError(err.message))
  }, [])

  if (error) return <Alert error={error} />
  if (!inventory) return <Loading />

  const byStage = (stage) => inventory.filter((row) => row.item.type === stage)

  return (
    <>
      <div className="stat-grid">
        {STAGES.map((s) => {
          const rows = byStage(s.key)
          return (
            <div className="stat" key={s.key}>
              <div className="label">{s.label}</div>
              <div className="value">{rows.length}</div>
              <div className="muted" style={{ fontSize: 12, marginTop: 2 }}>
                {rows.filter((r) => r.is_low_stock).length} at or below reorder level
              </div>
            </div>
          )
        })}
        <div className="stat">
          <div className="label">Low Stock Alerts</div>
          <div className="value" style={{ color: lowStock.length ? 'var(--red)' : 'var(--green)' }}>
            {lowStock.length}
          </div>
          <div className="muted" style={{ fontSize: 12, marginTop: 2 }}>across all stages</div>
        </div>
      </div>

      {STAGES.map((s) => (
        <Panel title={`${s.label} — current inventory`} key={s.key}>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Name</th>
                  <th className="num">On hand</th>
                  <th className="num">Reorder level</th>
                  <th>Unit</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {byStage(s.key).map((row) => (
                  <tr key={row.item.id}>
                    <td className="mono">{row.item.sku}</td>
                    <td>{row.item.name}</td>
                    <td className={`num ${row.is_low_stock ? 'low' : ''}`}>{qty(row.quantity_on_hand)}</td>
                    <td className="num muted">{qty(row.reorder_level)}</td>
                    <td className="muted">{row.item.unit}</td>
                    <td>{row.is_low_stock ? <span className="low">Low</span> : <Pill value={row.item.type} />}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {byStage(s.key).length === 0 && <Empty>No stock records for this stage.</Empty>}
          </div>
        </Panel>
      ))}
    </>
  )
}
