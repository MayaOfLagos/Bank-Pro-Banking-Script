import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const backendOrigin = process.env.VITE_BACKEND_ORIGIN || 'http://localhost:3000'

/**
 * Dev serves the SPA at the root (/) so vue-router history mode works
 * naturally at http://localhost:5173/dashboard, /cards, /transactions/:id
 * with HMR. Production keeps /assets/user-app/ because Apache serves the
 * built bundle from that path.
 */
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/assets/user-app/' : '/',
  plugins: [vue()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: backendOrigin,
        changeOrigin: true
      },
      // Every runtime-served asset dir (settings/logo, profile avatars,
      // ID card uploads, deposit receipts, legacy /assets/img favicons)
      // lives on the PHP side. One catch-all rule keeps them all reachable.
      // /assets/user-app/* would also match but it's fine — the built
      // bundle on disk is what PHP would return anyway.
      '/assets': {
        target: backendOrigin,
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: '../../assets/user-app',
    emptyOutDir: true,
    cssCodeSplit: false,
    sourcemap: false,
    rollupOptions: {
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) return 'app.css'
          return 'assets/[name]-[hash][extname]'
        }
      }
    }
  }
}))
