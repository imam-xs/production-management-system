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

// "Plant Administrator" -> "PA". Falls back to a single character rather than
// rendering an empty circle while the session is still resolving.
function initialsOf(name) {
  const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase()
}

export default function Layout({ user, onLogout, children }) {
  const location = useLocation()
  const [lowStockCount, setLowStockCount] = useState(0)
  const [menuOpen, setMenuOpen] = useState(false)

  // Close the drawer after navigating, otherwise it stays over the page the
  // user just asked for. Wide screens ignore the flag entirely.
  useEffect(() => setMenuOpen(false), [location.pathname])

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
    <div className={`shell ${menuOpen ? 'menu-open' : ''}`}>
      <aside className="sidebar">
        <div className="brand">
          <button
            className="menu-toggle"
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((open) => !open)}
          >
            <span />
            <span />
            <span />
          </button>
          Production Management
          <span className="brand-sub">Steel fabrication plant</span>
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

        {/* Pinned to the bottom of the sidebar (margin-top: auto), which keeps
            the topbar for the one thing that changes as you navigate — where
            you are — rather than mixing it with who you are. */}
        <div className="sidebar-foot">
          <div className="sidebar-user">
            <span className="avatar">{initialsOf(user?.name)}</span>
            <span className="sidebar-user-name">{user?.name}</span>
          </div>
          <button className="sign-out" onClick={onLogout}>Sign out</button>
        </div>
      </aside>

      <div className="main">
        <header className="topbar">
          <h1>{title}</h1>
        </header>
        <main className="content">{children}</main>
      </div>
    </div>
  )
}
