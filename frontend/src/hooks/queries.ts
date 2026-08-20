import { skipToken, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '../api/client'
import type {
  Employee,
  Evaluation,
  EvaluationSummary,
  NewEvaluation,
  Question,
  Subordinate,
} from '../types/api'

export function useEmployees() {
  return useQuery({
    queryKey: ['employees'],
    queryFn: () => api.getPublic<Employee[]>('/employees'),
    staleTime: Infinity,
  })
}

/** Resolve o nome pelo catálogo já cacheado pelo seletor; vale até para quem nunca foi avaliado. */
export function useEmployeeName(employeeId: number): string {
  const { data: employees } = useEmployees()

  return employees?.find((employee) => employee.id === employeeId)?.name ?? `Funcionário #${employeeId}`
}

export function useQuestions() {
  return useQuery({
    queryKey: ['questions'],
    queryFn: () => api.getPublic<Question[]>('/questions'),
    staleTime: Infinity,
  })
}

export function useSubordinates(leaderId: number | null) {
  return useQuery({
    queryKey: ['subordinates', leaderId],
    // skipToken em vez de `enabled`: mantém o tipo estreitado sem asserção de não-nulo.
    queryFn:
      leaderId === null ? skipToken : () => api.get<Subordinate[]>('/me/subordinates', leaderId),
  })
}

export function useLatestEvaluation(leaderId: number, evaluatedId: number) {
  return useQuery({
    queryKey: ['latest-evaluation', leaderId, evaluatedId],
    queryFn: () => api.get<Evaluation>(`/employees/${evaluatedId}/evaluations/latest`, leaderId),
  })
}

export function useEvaluationHistory(leaderId: number, evaluatedId: number) {
  return useQuery({
    queryKey: ['evaluation-history', leaderId, evaluatedId],
    queryFn: () => api.get<EvaluationSummary[]>(`/employees/${evaluatedId}/evaluations`, leaderId),
  })
}

export function useCreateEvaluation(leaderId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: NewEvaluation) => api.post<Evaluation>('/evaluations', payload, leaderId),
    onSuccess: () => {
      // Prefixos, não chaves exatas: a nova avaliação pode mudar a vigente para qualquer líder
      // que enxergue o avaliado, e prefixo continua certo mesmo se o líder trocar em voo.
      void queryClient.invalidateQueries({ queryKey: ['subordinates'] })
      void queryClient.invalidateQueries({ queryKey: ['latest-evaluation'] })
      void queryClient.invalidateQueries({ queryKey: ['evaluation-history'] })
    },
  })
}
