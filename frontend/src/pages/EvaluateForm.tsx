import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { ApiError } from '../api/client'
import { Panel } from '../components/Panel'
import { parseEmployeeId, useCurrentLeader } from '../hooks/currentLeader'
import {
  useCreateEvaluation,
  useEmployeeName,
  useQuestions,
  useSubordinates,
} from '../hooks/queries'
import type { Question } from '../types/api'

const ANSWER_OPTIONS = [1, 2, 3, 4] as const

export function EvaluateForm() {
  const { id } = useParams()
  const evaluatedId = parseEmployeeId(id)
  const { leaderId } = useCurrentLeader()

  if (leaderId === null) {
    return <Panel>Escolha um líder no seletor acima antes de avaliar.</Panel>
  }

  if (evaluatedId === null) {
    return <Panel tone="error">Funcionário inválido.</Panel>
  }

  // A key remonta o formulário quando o líder ou o avaliado mudam: respostas preenchidas e
  // erros de envio nunca vazam de um contexto para o outro, e a avaliação sai sempre em nome
  // do líder que estava selecionado enquanto o formulário era preenchido.
  return (
    <FormContent key={`${leaderId}:${evaluatedId}`} leaderId={leaderId} evaluatedId={evaluatedId} />
  )
}

function FormContent({ leaderId, evaluatedId }: { leaderId: number; evaluatedId: number }) {
  const navigate = useNavigate()
  const questions = useQuestions()
  const subordinates = useSubordinates(leaderId)
  const evaluatedName = useEmployeeName(evaluatedId)
  const createEvaluation = useCreateEvaluation(leaderId)
  const [answers, setAnswers] = useState<Record<number, number>>({})

  if (questions.isPending) {
    return <Panel>Carregando…</Panel>
  }

  if (questions.isError) {
    return <Panel tone="error">{questions.error.message}</Panel>
  }

  // Poupa o usuário de preencher tudo para só então receber o 403: cobre a si mesmo, pares,
  // superiores e ids inexistentes com o mesmo critério do backend, que continua a autoridade.
  if (subordinates.data !== undefined && !subordinates.data.some((s) => s.id === evaluatedId)) {
    return <Panel tone="error">Este funcionário não faz parte da sua hierarquia.</Panel>
  }

  const complete =
    questions.data.length > 0 && questions.data.every((question) => answers[question.id] !== undefined)

  const submit = (event: React.FormEvent) => {
    event.preventDefault()

    // O botão desabilitado já bloqueia o clique, mas a submissão implícita por Enter cai aqui;
    // o isPending evita um segundo POST (e um 409 falso) antes do re-render.
    if (!complete || createEvaluation.isPending) {
      return
    }

    createEvaluation.mutate(
      {
        evaluated_id: evaluatedId,
        answers: questions.data.map((question) => ({
          question_id: question.id,
          answer: answers[question.id],
        })),
      },
      { onSuccess: () => void navigate(`/employees/${evaluatedId}`) },
    )
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <div>
        <Link to={`/employees/${evaluatedId}`} className="text-sm text-slate-500 hover:text-slate-700">
          ← voltar
        </Link>
        <h2 className="text-xl font-semibold">Avaliar {evaluatedName}</h2>
        <p className="text-sm text-slate-500">
          Notas de 1 (abaixo do esperado) a 4 (excepcional). Depois de enviada, a avaliação não
          pode ser alterada.
        </p>
      </div>

      <section className="divide-y divide-slate-100 rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
        {questions.data.map((question) => (
          <QuestionField
            key={question.id}
            question={question}
            value={answers[question.id]}
            onChange={(answer) => setAnswers((current) => ({ ...current, [question.id]: answer }))}
          />
        ))}
      </section>

      {createEvaluation.isError && <SubmitError error={createEvaluation.error} />}

      <div className="flex flex-wrap items-center justify-between gap-4">
        <ScorePreview questions={questions.data} answers={answers} complete={complete} />
        <button
          type="submit"
          disabled={!complete || createEvaluation.isPending}
          className="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-medium text-slate-50 hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
        >
          {createEvaluation.isPending ? 'Enviando…' : 'Enviar avaliação'}
        </button>
      </div>
    </form>
  )
}

function QuestionField({
  question,
  value,
  onChange,
}: {
  question: Question
  value: number | undefined
  onChange: (answer: number) => void
}) {
  return (
    <fieldset className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
      <legend className="float-left">
        <span className="text-sm font-medium">{question.name}</span>
        <span className="ml-2 text-xs text-slate-500">peso {question.weight}</span>
      </legend>

      <div className="flex gap-2">
        {ANSWER_OPTIONS.map((option) => (
          <label
            key={option}
            className={`flex size-10 cursor-pointer items-center justify-center rounded-md text-sm font-semibold ring-1 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500 ${
              value === option
                ? 'bg-slate-900 text-slate-50 ring-slate-900'
                : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
            }`}
          >
            <input
              type="radio"
              name={`question-${question.id}`}
              value={option}
              checked={value === option}
              onChange={() => onChange(option)}
              className="sr-only"
            />
            {option}
          </label>
        ))}
      </div>
    </fieldset>
  )
}

function ScorePreview({
  questions,
  answers,
  complete,
}: {
  questions: Question[]
  answers: Record<number, number>
  complete: boolean
}) {
  if (!complete) {
    const remaining = questions.filter((question) => answers[question.id] === undefined).length

    return (
      <p className="text-sm text-slate-500">
        {remaining} {remaining === 1 ? 'questão restante' : 'questões restantes'}
      </p>
    )
  }

  // Mesma fórmula da view evaluation_score: Σ(resposta × peso) / peso total.
  const totalWeight = questions.reduce((sum, question) => sum + question.weight, 0)
  const weighted = questions.reduce((sum, question) => sum + answers[question.id] * question.weight, 0)
  const score = (weighted / totalWeight).toFixed(2)

  return (
    <p className="text-sm text-slate-600">
      Nota final: <span className="font-semibold tabular-nums">{score}</span>
    </p>
  )
}

function SubmitError({ error }: { error: Error }) {
  const isWeeklyLimit = error instanceof ApiError && error.status === 409

  return (
    <div className="rounded-lg bg-red-50 px-5 py-4 text-sm text-red-800 ring-1 ring-red-200">
      <p className="font-medium">{error.message}</p>
      {isWeeklyLimit && (
        <p className="mt-1 text-red-700">
          O limite é de uma avaliação por semana para cada par avaliador/avaliado. Você pode
          avaliar este funcionário novamente na próxima semana.
        </p>
      )}
    </div>
  )
}
