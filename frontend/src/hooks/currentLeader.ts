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

/**
 * Único parse de id do front — seletor, storage e parâmetros de rota passam por aqui.
 * O regex espelha o CurrentEmployeeMiddleware do backend: só decimal canônico, nada de
 * '0x10', '1e2' ou espaços que o Number() aceitaria.
 */
export function parseEmployeeId(value: string | null | undefined): number | null {
  if (value === null || value === undefined || !/^[1-9][0-9]*$/.test(value)) {
    return null
  }

  return Number(value)
}

// localStorage pode lançar (cookies bloqueados, contexto embutido); sem ele a app funciona,
// só não lembra o líder entre reloads.
export function readStoredLeaderId(): number | null {
  try {
    return parseEmployeeId(localStorage.getItem(LEADER_STORAGE_KEY))
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
