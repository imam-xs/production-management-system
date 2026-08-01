// Small shared pieces used across pages. Kept in one file on purpose — each is
// a handful of lines and splitting them into separate modules would add more
// navigation than clarity.

import { useEffect, useRef } from 'react'

export function Panel({ title, actions, children, padded = false }) {
  return (
    <div className="panel">
      {(title || actions) && (
        <div className="panel-head">
          <h2>{title}</h2>
          <div className="row shrink" style={{ gap: 8 }}>{actions}</div>
        </div>
      )}
      {padded ? <div className="panel-body">{children}</div> : children}
    </div>
  )
}

export function Field({ label, error, children }) {
  return (
    <div className="field">
      <label>{label}</label>
      {children}
      {error && <div className="err">{error}</div>}
    </div>
  )
}

export function Pill({ value }) {
  if (!value) return <span className="muted">—</span>
  return <span className={`pill ${value}`}>{String(value).replace(/_/g, ' ')}</span>
}

/**
 * Success messages clear themselves; errors do not.
 *
 * A confirmation the user has already read is just clutter, but a failure they
 * missed is a failure they will repeat — so an error stays until the next action
 * replaces it. Pages opt in to the timer by passing `onClearSuccess`.
 */
export function Alert({ error, success, onClearSuccess, timeout = 6000 }) {
  // Held in a ref so an inline arrow callback does not restart the timer on
  // every render — the effect depends on the message itself, not the handler.
  const clear = useRef(onClearSuccess)
  clear.current = onClearSuccess

  useEffect(() => {
    if (!success) return
    const id = setTimeout(() => clear.current?.(), timeout)
    return () => clearTimeout(id)
  }, [success, timeout])

  if (error) return <div className="alert error">{error}</div>
  if (success) return <div className="alert ok">{success}</div>
  return null
}

export function Loading({ children = 'Loading…' }) {
  return <div className="empty">{children}</div>
}

export function Empty({ children = 'Nothing to show yet.' }) {
  return <div className="empty">{children}</div>
}

export function Modal({ title, onClose, onSubmit, submitting, submitLabel = 'Save', children }) {
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">{title}</div>
        <form
          onSubmit={(e) => {
            e.preventDefault()
            onSubmit()
          }}
        >
          <div className="modal-body">{children}</div>
          <div className="modal-foot">
            <button type="button" onClick={onClose}>Cancel</button>
            <button type="submit" className="primary" disabled={submitting}>
              {submitting ? 'Working…' : submitLabel}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// Quantities arrive from the API as decimal strings (never floats) so the exact
// value survives. Trim the trailing zeros only for display.
export function qty(value) {
  if (value === null || value === undefined) return '—'
  const n = String(value)
  return n.includes('.') ? n.replace(/0+$/, '').replace(/\.$/, '') : n
}

export function when(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}
