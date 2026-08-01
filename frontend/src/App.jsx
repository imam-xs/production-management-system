import { useEffect, useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { api, getToken, setToken } from './api.js'
import Layout from './components/Layout.jsx'
import { Loading } from './components/ui.jsx'
import Batches from './pages/Batches.jsx'
import Dashboard from './pages/Dashboard.jsx'
import EventLog from './pages/EventLog.jsx'
import Items from './pages/Items.jsx'
import Login from './pages/Login.jsx'
import Production from './pages/Production.jsx'
import ReceiveStock from './pages/ReceiveStock.jsx'
import Trace from './pages/Trace.jsx'

export default function App() {
  const [user, setUser] = useState(null)
  const [checking, setChecking] = useState(true)

  // A token in localStorage may be stale (revoked by logout elsewhere, or the
  // database was reseeded), so it is validated against /auth/me before the app
  // renders rather than trusted on sight.
  useEffect(() => {
    if (!getToken()) {
      setChecking(false)
      return
    }
    api
      .get('/auth/me')
      .then((res) => setUser(res.data))
      .catch(() => setToken(null))
      .finally(() => setChecking(false))
  }, [])

  function logout() {
    api.post('/auth/logout').catch(() => {})
    setToken(null)
    setUser(null)
  }

  if (checking) return <Loading>Checking session…</Loading>
  if (!user) return <Login onLoggedIn={setUser} />

  return (
    <Layout user={user} onLogout={logout}>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route
          path="/raw-materials"
          element={<Items endpoint="/raw-materials" title="Raw Materials" noun="Raw material" />}
        />
        <Route
          path="/semi-finished"
          element={<Items endpoint="/semi-finished-products" title="Semi-Finished Products" noun="Semi-finished product" />}
        />
        <Route
          path="/finished"
          element={<Items endpoint="/finished-products" title="Finished Products" noun="Finished product" />}
        />
        <Route path="/receive" element={<ReceiveStock />} />
        <Route path="/production" element={<Production />} />
        <Route path="/batches" element={<Batches />} />
        <Route path="/trace/:batchId" element={<Trace />} />
        <Route path="/events" element={<EventLog />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Layout>
  )
}
