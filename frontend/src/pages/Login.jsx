import { useState } from 'react'
import { api, setToken } from '../api.js'
import { Alert, Field } from '../components/ui.jsx'

export default function Login({ onLoggedIn }) {
  const [email, setEmail] = useState('admin@pms.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      const res = await api.post('/auth/login', { email, password })
      setToken(res.data.token)
      onLoggedIn(res.data.user)
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={submit}>
        <h1>Production Management</h1>
        <p className="sub">Sign in to continue</p>

        <Alert error={error} />

        <Field label="Email">
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </Field>
        <Field label="Password">
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </Field>

        <button type="submit" className="primary" style={{ width: '100%' }} disabled={busy}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>

        <div className="login-hint">
          Seeded account: <span className="mono">admin@pms.test</span> / <span className="mono">password</span>
        </div>
      </form>
    </div>
  )
}
