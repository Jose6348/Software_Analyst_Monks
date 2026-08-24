import { EmployeeTree } from '../components/EmployeeTree'
import { Panel } from '../components/Panel'
import { useCurrentLeader } from '../hooks/currentLeader'
import { useSubordinates } from '../hooks/queries'

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

  const directs = subordinates.filter((subordinate) => subordinate.is_direct).length
  const evaluated = subordinates.filter((subordinate) => subordinate.latest_score !== null).length

  return (
    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
      <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 px-6 py-4">
        <h2 className="font-semibold">Sua hierarquia</h2>
        <span className="text-sm text-slate-500">
          {subordinates.length} {subordinates.length === 1 ? 'liderado' : 'liderados'} ·{' '}
          {directs} {directs === 1 ? 'direto' : 'diretos'} · {evaluated} com avaliação
        </span>
      </div>

      <EmployeeTree subordinates={subordinates} leaderId={leaderId} />
    </section>
  )
}
