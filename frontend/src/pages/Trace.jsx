import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api.js'
import { Alert, Loading, Panel, Pill, qty, when } from '../components/ui.jsx'

// renders the recursive trace tree the API returns
function TraceNode({ node, depth = 0 }) {
  return (
    <div className={`tree-node ${depth === 0 ? 'root' : ''}`}>
      <div className="tree-card">
        <div className="head">
          <span className="mono"><strong>{node.batch_number}</strong></span>
          <Pill value={node.item?.type} />
          <Pill value={node.origin} />
        </div>
        <div className="meta">
          {node.item?.name} ({node.item?.sku}) · produced {qty(node.quantity_produced)} · remaining{' '}
          {qty(node.quantity_remaining)} · {when(node.produced_at)}
          {node.production_order_number && (
            <>
              {' · order '}
              <span className="mono">{node.production_order_number}</span>
            </>
          )}
        </div>
      </div>

      {node.consumed?.length > 0 && (
        <>
          <div className="consumed-label">consumed {node.consumed.length} input batch(es)</div>
          {node.consumed.map((edge, i) => (
            <div key={i}>
              <div className="muted" style={{ fontSize: 12, marginLeft: 14 }}>
                ↳ used <strong>{qty(edge.quantity_consumed)}</strong> of:
              </div>
              <TraceNode node={edge.batch} depth={depth + 1} />
            </div>
          ))}
        </>
      )}

      {node.origin === 'purchase' && depth > 0 && (
        <div className="muted" style={{ fontSize: 12, marginLeft: 14, paddingBottom: 6 }}>
          ● originating raw material, so the trace ends here
        </div>
      )}
    </div>
  )
}

export default function Trace() {
  const { batchId } = useParams()
  const navigate = useNavigate()
  const [tree, setTree] = useState(null)
  const [downstream, setDownstream] = useState(null)
  const [error, setError] = useState('')
  const [view, setView] = useState('upstream')

  useEffect(() => {
    setTree(null)
    setDownstream(null)
    setError('')
    api
      .get(`/batches/${batchId}/trace`)
      .then((res) => setTree(res.data))
      .catch((err) => setError(err.message))
    api
      .get(`/batches/${batchId}/trace-downstream`)
      .then((res) => setDownstream(res.data))
      .catch(() => {})
  }, [batchId])

  if (error) return <Alert error={error} />
  if (!tree) return <Loading />

  return (
    <>
      <Panel
        title={`Traceability: ${tree.batch_number}`}
        actions={
          <>
            <button className={view === 'upstream' ? 'primary sm' : 'sm'} onClick={() => setView('upstream')}>
              Upstream (what it came from)
            </button>
            <button className={view === 'downstream' ? 'primary sm' : 'sm'} onClick={() => setView('downstream')}>
              Downstream (where it went)
            </button>
            <button className="sm" onClick={() => navigate('/batches')}>Back to batches</button>
          </>
        }
        padded
      >
        {view === 'upstream' ? (
          <div className="tree">
            <TraceNode node={tree} />
          </div>
        ) : (
          <div className="tree">
            {!downstream ? (
              <Loading />
            ) : downstream.used_in?.length ? (
              <>
                <div className="tree-card">
                  <div className="head">
                    <span className="mono"><strong>{downstream.batch_number}</strong></span>
                    <Pill value={downstream.item?.type} />
                  </div>
                  <div className="meta">{downstream.item?.name}</div>
                </div>
                <div className="consumed-label">used in {downstream.used_in.length} production order(s)</div>
                {downstream.used_in.map((u, i) => (
                  <div className="tree-node" key={i}>
                    <div className="tree-card">
                      <div className="head">
                        <span className="mono"><strong>{u.order_number}</strong></span>
                      </div>
                      <div className="meta">
                        consumed {qty(u.quantity_consumed)}
                        {u.output_batch && (
                          <>
                            {' → produced '}
                            <span className="mono">{u.output_batch.batch_number}</span>
                            {' ('}{u.output_batch.item?.name}{')'}
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </>
            ) : (
              <p className="muted">This batch has not been consumed by any production order yet.</p>
            )}
          </div>
        )}
      </Panel>
    </>
  )
}
