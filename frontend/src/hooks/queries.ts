import { skipToken, useQuery } from '@tanstack/react-query'

import { api } from '../api/client'
import type { Employee, Subordinate } from '../types/api'

export function useEmployees() {
  return useQuery({
    queryKey: ['employees'],
    queryFn: () => api.getPublic<Employee[]>('/employees'),
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
