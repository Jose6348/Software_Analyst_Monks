import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// O proxy espelha o que o nginx faz em producao: sem ele o dev server em :5173 faria
// requisicao cross-origin para :8080 e o browser bloquearia (nao ha CORS na API por escolha).
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      // A porta acompanha API_HOST_PORT do compose; sem a variavel, o padrao 8080.
      '/api': `http://localhost:${process.env.API_HOST_PORT ?? '8080'}`,
    },
  },
})
