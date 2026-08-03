import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { Alert, Empty, Loading, Panel, qty, when } from '../components/ui.jsx'

// every row on this page was written by the RabbitMQ consumer running in a separate process, never by an HTTP request
export default function EventLog() {
  const [rows, setRows] = useState(null)
  const [error, setError] = useState('')
  const [auto, setAuto] = useState(true)

  async function load() {
    try {
      // The API is paginated; the demo dataset fits one page, so the UI asks
      // for a large per_page rather than rendering pager controls.
      const res = await api.get('/production-events?per_page=50')
      setRows(res.data)
      setError('')
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    load()
  }, [])

  useEffect(() => {
    if (!auto) return
    const id = setInterval(load, 4000)
    return () => clearInterval(id)
  }, [auto])

  return (
    <>
      <Alert error={error} />

      <Panel
        title="Production events consumed from RabbitMQ"
        actions={
          <>
            <label className="shrink muted" style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
              <input type="checkbox" checked={auto} onChange={(e) => setAuto(e.target.checked)} style={{ width: 'auto' }} />
              Auto-refresh
            </label>
            <button className="sm" onClick={load}>Refresh</button>
          </>
        }
      >
        {rows === null ? (
          <Loading />
        ) : rows.length === 0 ? (
          <Empty>
            No events processed yet. Execute a production order and the worker
            will record it here within a second or two.
          </Empty>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Routing key</th>
                  <th>Order</th>
                  <th>Output batch</th>
                  <th className="num">Inputs</th>
                  <th className="num">Attempts</th>
                  <th>Occurred</th>
                  <th>Processed</th>
                  <th className="num">Lag</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((e) => (
                  <tr key={e.id}>
                    <td>{e.event_type}</td>
                    <td className="mono muted">{e.routing_key}</td>
                    <td className="mono">{e.order_number ?? '—'}</td>
                    <td className="mono">{e.payload?.output?.batch_number ?? '—'}</td>
                    <td className="num">{e.payload?.consumed?.length ?? 0}</td>
                    <td className="num">{e.attempts}</td>
                    <td className="muted">{when(e.occurred_at)}</td>
                    <td className="muted">{when(e.processed_at)}</td>
                    <td className="num muted">{e.lag_seconds}s</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Panel title="Where these rows come from" padded>
        <p className="muted" style={{ margin: 0, fontSize: 13, lineHeight: 1.6 }}>
          These rows are not written by the API. It drops a message on RabbitMQ
          and replies right away; the worker container picks the message up a
          moment later and writes the row. Try it: stop the worker, then run an
          order. The order works fine, but nothing shows up here until you start
          the worker again.
        </p>
      </Panel>
    </>
  )
}
