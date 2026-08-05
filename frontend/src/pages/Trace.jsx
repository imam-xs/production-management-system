import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api.js'
import { Alert, day, Loading, Panel, Pill, qty } from '../components/ui.jsx'

// renders the recursive trace tree the API returns.
// usedQuantity is how much of THIS batch the parent took, so it belongs on the
// card itself rather than on a separate line above it
function TraceNode({ node, depth = 0, usedQuantity = null }) {
  const inputs = node.consumed ?? []
  const isOrigin = node.origin === 'purchase'

  return (
    <div className={`tree-node ${depth === 0 ? 'root' : ''}`}>
      <div className={`tree-card ${isOrigin && depth > 0 ? 'origin' : ''}`}>
        <div className="head">
          {usedQuantity !== null && <span className="used">used {qty(usedQuantity)}</span>}
          <span className="mono"><strong>{node.batch_number}</strong></span>
          <Pill value={node.item?.type} />
          <Pill value={node.origin} />
        </div>

        <div className="name">
          {node.item?.name} <span className="mono muted">{node.item?.sku}</span>
        </div>

        <div className="meta">
          {qty(node.quantity_produced)} produced, {qty(node.quantity_remaining)} still on hand
          {' · '}{day(node.produced_at)}
          {node.production_order_number && (
            <>{' · '}<span className="mono">{node.production_order_number}</span></>
          )}
        </div>
      </div>

      {inputs.length > 0 && (
        <>
          <div className="consumed-label">
            made from {inputs.length} {inputs.length === 1 ? 'batch' : 'batches'}
          </div>
          {inputs.map((edge, i) => (
            <TraceNode key={i} node={edge.batch} depth={depth + 1} usedQuantity={edge.quantity_consumed} />
          ))}
        </>
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
                <div className="consumed-label">
                  used in {downstream.used_in.length} production{' '}
                  {downstream.used_in.length === 1 ? 'order' : 'orders'}
                </div>
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
