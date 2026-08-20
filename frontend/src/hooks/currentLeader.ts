import { createContext, use } from 'react'

export const LEADER_STORAGE_KEY = 'currentEmployeeId'

export interface CurrentLeader {
  leaderId: number | null
  setLeaderId: (id: number) => void
}

export const CurrentLeaderContext = createContext<CurrentLeader | null>(null)

export function useCurrentLeader(): CurrentLeader {
  const value = use(CurrentLeaderContext)

  if (value === null) {
    throw new Error('useCurrentLeader precisa estar dentro de CurrentLeaderProvider.')
  }

  return value
}

/** Único parse de id do front: o seletor e o storage passam pela mesma validação. */
export function parseLeaderId(value: string | null): number | null {
  if (value === null) {
    return null
  }

  const parsed = Number(value)

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

// localStorage pode lançar (cookies bloqueados, contexto embutido); sem ele a app funciona,
// só não lembra o líder entre reloads.
export function readStoredLeaderId(): number | null {
  try {
    return parseLeaderId(localStorage.getItem(LEADER_STORAGE_KEY))
  } catch {
    return null
  }
}

export function writeStoredLeaderId(id: number): void {
  try {
    localStorage.setItem(LEADER_STORAGE_KEY, String(id))
  } catch {
    // escolha vale só para a sessão atual
  }
}
