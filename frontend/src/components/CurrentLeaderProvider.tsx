import { useCallback, useMemo, useState, type ReactNode } from 'react'

import {
  CurrentLeaderContext,
  readStoredLeaderId,
  writeStoredLeaderId,
  type CurrentLeader,
} from '../hooks/currentLeader'

/**
 * Simula a autenticação: o líder atual vive em `localStorage` e é enviado no header
 * `X-Employee-Id`. Num sistema real este seria o ponto onde a sessão entraria.
 */
export function CurrentLeaderProvider({ children }: { children: ReactNode }) {
  const [leaderId, setLeaderIdState] = useState<number | null>(readStoredLeaderId)

  const setLeaderId = useCallback((id: number) => {
    writeStoredLeaderId(id)
    setLeaderIdState(id)
  }, [])

  const value = useMemo<CurrentLeader>(() => ({ leaderId, setLeaderId }), [leaderId, setLeaderId])

  return <CurrentLeaderContext value={value}>{children}</CurrentLeaderContext>
}
