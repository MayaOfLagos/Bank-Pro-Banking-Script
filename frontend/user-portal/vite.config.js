import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const backendOrigin = process.env.VITE_BACKEND_ORIGIN || 'http://localhost:3000'

export default defineConfig({
  base: '/assets/user-app/',
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
      '/assets/profile': {
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
})
