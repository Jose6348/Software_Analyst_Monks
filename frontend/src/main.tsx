import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'

import { App } from './App'
import { CurrentLeaderProvider } from './components/CurrentLeaderProvider'
import './index.css'

// Erros da API sao de negocio (403, 409) e nao melhoram com retry.
const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: false } },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <CurrentLeaderProvider>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </CurrentLeaderProvider>
    </QueryClientProvider>
  </StrictMode>,
)
