import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    // host: true binds 0.0.0.0 so the dev server is reachable from outside the
    // container. usePolling is needed because file-change events do not cross
    // a Windows bind mount reliably.
    host: true,
    port: 5173,
    watch: { usePolling: true },
  },
})
