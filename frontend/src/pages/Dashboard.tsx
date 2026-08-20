import { useSubordinates } from '../hooks/queries'
import { ScoreBadge } from '../components/ScoreBadge'
import { useCurrentLeader } from '../hooks/currentLeader'
import type { Subordinate } from '../types/api'

export function Dashboard() {
  const { leaderId } = useCurrentLeader()
  const { data: subordinates, isPending, isError, error } = useSubordinates(leaderId)

  if (leaderId === null) {
    return (
      <Panel>
        Escolha um líder no seletor acima para ver a hierarquia dele. A identificação fica salva no
        navegador e acompanha todas as requisições.
      </Panel>
    )
  }

  if (isPending) {
    return <Panel>Carregando…</Panel>
  }

  if (isError) {
    return <Panel tone="error">{error.message}</Panel>
  }

  if (subordinates.length === 0) {
    return <Panel>Este funcionário não lidera ninguém, então não tem quem avaliar.</Panel>
  }

  return (
    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
      <div className="flex items-baseline justify-between border-b border-slate-200 px-6 py-4">
        <h2 className="font-semibold">Sua hierarquia</h2>
        <span className="text-sm text-slate-500">
          {subordinates.length} {subordinates.length === 1 ? 'liderado' : 'liderados'}
        </span>
      </div>

      <ul className="divide-y divide-slate-100">
        {subordinates.map((subordinate) => (
          <SubordinateRow key={subordinate.id} subordinate={subordinate} />
        ))}
      </ul>
    </section>
  )
}

function SubordinateRow({ subordinate }: { subordinate: Subordinate }) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
      <div className="min-w-0">
        <p className="truncate font-medium">{subordinate.name}</p>
        <p className="truncate text-sm text-slate-500">{subordinate.position_name}</p>
      </div>

      <div className="flex items-center gap-3">
        <span className="text-xs text-slate-500">
          {subordinate.is_direct ? 'direto' : `indireto · ${subordinate.depth} níveis`}
        </span>
        <ScoreBadge score={subordinate.latest_score} />
      </div>
    </li>
  )
}

function Panel({ children, tone = 'neutral' }: { children: string; tone?: 'neutral' | 'error' }) {
  const toneClasses =
    tone === 'error' ? 'text-red-800 ring-red-200 bg-red-50' : 'text-slate-600 ring-slate-200 bg-white'

  return <p className={`rounded-lg px-6 py-8 text-center shadow-sm ring-1 ${toneClasses}`}>{children}</p>
}
