import { parseEmployeeId, useCurrentLeader } from '../hooks/currentLeader'
import { useEmployees } from '../hooks/queries'

export function LeaderSwitcher() {
  const { leaderId, setLeaderId } = useCurrentLeader()
  const { data: employees, isPending, isError } = useEmployees()

  if (isError) {
    return <span className="text-sm text-red-100">Não foi possível carregar os funcionários.</span>
  }

  // Um value sem <option> correspondente renderia o select em branco: enquanto a lista não
  // chega — ou se o id salvo não existe mais — o placeholder assume.
  const knownLeaderId = employees?.some((employee) => employee.id === leaderId) ? leaderId : null

  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="text-slate-300">Acessando como</span>
      <select
        className="rounded-md border border-slate-600 bg-slate-800 px-3 py-1.5 text-slate-50 disabled:opacity-50"
        value={knownLeaderId ?? ''}
        disabled={isPending}
        onChange={(event) => {
          const id = parseEmployeeId(event.target.value)

          if (id !== null) {
            setLeaderId(id)
          }
        }}
      >
        <option value="" disabled>
          {isPending ? 'Carregando…' : 'Selecione um líder'}
        </option>
        {employees?.map((employee) => (
          <option key={employee.id} value={employee.id}>
            {employee.name} — {employee.position_name}
          </option>
        ))}
      </select>
    </label>
  )
}
