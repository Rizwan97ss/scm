import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    // Bind all interfaces and allow any *.localtest.me host — tenant
    // subdomains (e.g. riverside-demo.localtest.me) resolve to 127.0.0.1 via
    // wildcard DNS, but Vite's dev-server Host-header check (DNS rebinding
    // protection) rejects unrecognized hostnames unless explicitly allowed.
    host: true,
    allowedHosts: ['localhost', '.localtest.me'],
  },
})
