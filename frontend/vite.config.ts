import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// O proxy espelha o que o nginx faz em producao: sem ele o dev server em :5173 faria
// requisicao cross-origin para :8080 e o browser bloquearia (nao ha CORS na API por escolha).
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
})
