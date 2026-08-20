import { Link, useParams } from 'react-router-dom'

import { ApiError } from '../api/client'
import { Panel } from '../components/Panel'
import { ScoreBadge } from '../components/ScoreBadge'
import { parseEmployeeId, useCurrentLeader } from '../hooks/currentLeader'
import { useEmployeeName, useEvaluationHistory, useLatestEvaluation } from '../hooks/queries'
import type { Evaluation, EvaluationSummary } from '../types/api'

export function EmployeeDetail() {
  const { id } = useParams()
  const evaluatedId = parseEmployeeId(id)
  const { leaderId } = useCurrentLeader()

  if (leaderId === null) {
    return <Panel>Escolha um líder no seletor acima para consultar avaliações.</Panel>
  }

  if (evaluatedId === null) {
    return <Panel tone="error">Funcionário inválido.</Panel>
  }

  return <DetailContent leaderId={leaderId} evaluatedId={evaluatedId} />
}

function DetailContent({ leaderId, evaluatedId }: { leaderId: number; evaluatedId: number }) {
  const latest = useLatestEvaluation(leaderId, evaluatedId)
  const history = useEvaluationHistory(leaderId, evaluatedId)
  const evaluatedName = useEmployeeName(evaluatedId)

  // Um erro cacheado em refetch (ex.: o 404 de "nunca avaliado" logo após enviar a primeira
  // avaliação) não é resposta final; sem isso a página piscaria o estado antigo.
  const refreshing = (latest.isError && latest.isFetching) || (history.isError && history.isFetching)

  if (latest.isPending || history.isPending || refreshing) {
    return <Panel>Carregando…</Panel>
  }

  // 403/404 valem para o funcionário inteiro; o histórico traz a mensagem mais específica.
  if (history.isError) {
    return <Panel tone="error">{history.error.message}</Panel>
  }

  const neverEvaluated = latest.isError && latest.error instanceof ApiError && latest.error.status === 404

  if (latest.isError && !neverEvaluated) {
    return <Panel tone="error">{latest.error.message}</Panel>
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/" className="text-sm text-slate-500 hover:text-slate-700">
            ← voltar à hierarquia
          </Link>
          <h2 className="text-xl font-semibold">{evaluatedName}</h2>
        </div>
        <Link
          to={`/employees/${evaluatedId}/evaluate`}
          className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-slate-50 hover:bg-slate-700"
        >
          Avaliar
        </Link>
      </div>

      {latest.data ? (
        <CurrentEvaluation evaluation={latest.data} />
      ) : (
        <Panel>Este funcionário ainda não foi avaliado.</Panel>
      )}

      {history.data.length > 0 && <History entries={history.data} />}
    </div>
  )
}

function CurrentEvaluation({ evaluation }: { evaluation: Evaluation }) {
  return (
    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
        <div>
          <h3 className="font-semibold">Avaliação vigente</h3>
          <p className="text-sm text-slate-500">
            por {evaluation.evaluator.name} · {formatDate(evaluation.created_at)}
          </p>
        </div>
        <ScoreBadge score={evaluation.score} />
      </div>

      <ul className="divide-y divide-slate-100">
        {evaluation.answers.map((answer) => (
          <li key={answer.question_id} className="flex items-center justify-between gap-4 px-6 py-3">
            <div className="min-w-0">
              <p className="text-sm font-medium">{answer.question_name}</p>
              <p className="text-xs text-slate-500">peso {answer.weight}</p>
            </div>
            <AnswerDots value={answer.answer} />
          </li>
        ))}
      </ul>
    </section>
  )
}

function AnswerDots({ value }: { value: number }) {
  return (
    <div className="flex items-center gap-1.5" aria-label={`nota ${value} de 4`}>
      {[1, 2, 3, 4].map((step) => (
        <span
          key={step}
          className={`size-2.5 rounded-full ${step <= value ? 'bg-slate-700' : 'bg-slate-200'}`}
        />
      ))}
      <span className="ml-1 text-sm font-semibold tabular-nums">{value}</span>
    </div>
  )
}

function History({ entries }: { entries: EvaluationSummary[] }) {
  return (
    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
      <h3 className="border-b border-slate-200 px-6 py-4 font-semibold">Histórico</h3>
      <ul className="divide-y divide-slate-100">
        {entries.map((entry) => (
          <li key={entry.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
            <div>
              <p className="text-sm font-medium">{entry.evaluator.name}</p>
              <p className="text-xs text-slate-500">{formatDate(entry.created_at)}</p>
            </div>
            <div className="flex items-center gap-3">
              {entry.is_current && (
                <span className="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-slate-50">
                  vigente
                </span>
              )}
              <ScoreBadge score={entry.score} />
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}

// Em UTC de propósito: a semana da trava é ISO/UTC, e um usuário a oeste de Greenwich veria a
// data local "voltar um dia" e contradizer a mensagem do limite semanal.
function formatDate(isoDate: string): string {
  return new Date(isoDate).toLocaleDateString('pt-BR', { dateStyle: 'medium', timeZone: 'UTC' })
}
