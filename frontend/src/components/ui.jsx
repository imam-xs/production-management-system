// small shared pieces used across pages

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

// success messages clear themselves; errors do not
export function Alert({ error, success, onClearSuccess, timeout = 6000 }) {
  // held in a ref so an inline arrow callback does not restart the timer on
  // every render — the effect depends on the message itself, not the handler
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

// deliberately does NOT close on a backdrop click
export function Modal({ title, onClose, onSubmit, submitting, submitLabel = 'Save', children }) {
  // held in a ref so an inline arrow from the caller does not re-register the listener on every render
  const close = useRef(onClose)
  close.current = onClose

  useEffect(() => {
    function onKeyDown(event) {
      if (event.key === 'Escape') close.current()
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  return (
    <div className="modal-backdrop">
      <div className="modal" role="dialog" aria-modal="true" aria-label={title}>
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

// confirmation for a destructive action
export function Confirm({
  title,
  message,
  detail,
  confirmLabel = 'Delete',
  cancelLabel = 'Cancel',
  onConfirm,
  onCancel,
  busy = false,
  disabled = false,
}) {
  const cancel = useRef(onCancel)
  cancel.current = onCancel

  useEffect(() => {
    function onKeyDown(event) {
      if (event.key === 'Escape') cancel.current()
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  return (
    <div className="modal-backdrop">
      <div className="modal confirm" role="alertdialog" aria-modal="true" aria-label={title}>
        <div className="modal-head">{title}</div>
        <div className="modal-body">
          <p className="confirm-message">{message}</p>
          {detail && <p className="confirm-detail">{detail}</p>}
        </div>
        <div className="modal-foot">
          <button type="button" onClick={onCancel} disabled={busy}>{cancelLabel}</button>
          <button
            type="button"
            className="danger-solid"
            onClick={onConfirm}
            disabled={busy || disabled}
            autoFocus={!disabled}
          >
            {busy ? 'Working…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

// quantities arrive from the API as decimal strings (never floats) so the exact
// value survives. trim the trailing zeros only for display
export function qty(value) {
  if (value === null || value === undefined) return '—'
  const n = String(value)
  return n.includes('.') ? n.replace(/0+$/, '').replace(/\.$/, '') : n
}

export function when(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

// date only, for places where the time of day carries no meaning
export function day(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}
