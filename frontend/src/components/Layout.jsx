import { useEffect, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { api } from '../api.js'

// Grouped to mirror the domain rather than the API surface — a reviewer opening
// the UI should be able to see the production workflow in the navigation.
const NAV = [
  {
    group: null,
    links: [{ to: '/', label: 'Dashboard', end: true }],
  },
  {
    group: 'Inventory',
    links: [
      { to: '/raw-materials', label: 'Raw Materials', lowStock: true },
      { to: '/semi-finished', label: 'Semi-Finished' },
      { to: '/finished', label: 'Finished Goods' },
      { to: '/receive', label: 'Receive Stock' },
    ],
  },
  {
    group: 'Production',
    links: [{ to: '/production', label: 'Production Orders' }],
  },
  {
    group: 'Traceability',
    links: [
      { to: '/batches', label: 'Batches' },
      { to: '/events', label: 'Event Log' },
    ],
  },
]

const TITLES = {
  '/': 'Dashboard',
  '/raw-materials': 'Raw Materials',
  '/semi-finished': 'Semi-Finished Products',
  '/finished': 'Finished Products',
  '/receive': 'Receive Stock',
  '/production': 'Production Orders',
  '/batches': 'Batches',
  '/events': 'Event Log',
}

export default function Layout({ user, onLogout, children }) {
  const location = useLocation()
  const [lowStockCount, setLowStockCount] = useState(0)

  // Raw materials only: the badge sits beside that link, so counting every
  // stage would show a number the page it labels cannot account for.
  // Shortages in the other stages are visible on the Dashboard.
  //
  // Refreshed on navigation so the badge reflects the effect of whatever the
  // user just did (receiving stock, running production).
  useEffect(() => {
    api
      .get('/inventory/low-stock?type=raw')
      .then((res) => setLowStockCount(res.data.length))
      .catch(() => {})
  }, [location.pathname])

  const title = TITLES[location.pathname] || (location.pathname.startsWith('/trace') ? 'Traceability' : 'Production Management')

  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand">
          Production Management
          <span>Steel fabrication plant</span>
        </div>

        {NAV.map((section, i) => (
          <div className="nav-group" key={i}>
            {section.group && <div className="nav-group-label">{section.group}</div>}
            {section.links.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.end}
                className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
              >
                <span>{link.label}</span>
                {link.lowStock && lowStockCount > 0 && <span className="badge-count">{lowStockCount}</span>}
              </NavLink>
            ))}
          </div>
        ))}
      </aside>

      <div className="main">
        <header className="topbar">
          <h1>{title}</h1>
          <div className="row shrink" style={{ gap: 12, alignItems: 'center' }}>
            <span className="who">{user?.name}</span>
            <button className="sm" onClick={onLogout}>Sign out</button>
          </div>
        </header>
        <main className="content">{children}</main>
      </div>
    </div>
  )
}
