import type { ReactNode } from 'react'

import { LeaderSwitcher } from './LeaderSwitcher'

export function AppLayout({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen text-slate-900">
      <header className="bg-slate-900">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-4">
          <h1 className="text-lg font-semibold text-slate-50">Avaliação de Liderados</h1>
          <LeaderSwitcher />
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-8">{children}</main>
    </div>
  )
}
