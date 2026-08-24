import { Link } from 'react-router-dom'

import { ScoreBadge } from './ScoreBadge'
import type { Subordinate } from '../types/api'

interface TreeNode {
  subordinate: Subordinate
  children: TreeNode[]
}

/**
 * Remonta a árvore a partir da lista achatada, usando o `leader_id` de cada descendente.
 *
 * A marcação de visitado acontece antes de descer: `leader_lead` admite ciclos, e num
 * Henry → James → Henry a recursão voltaria ao ponto de partida. Marcar depois deixaria a
 * recursão reivindicar um irmão que o laço de fora ainda vai percorrer, duplicando a pessoa.
 */
function buildTree(subordinates: Subordinate[], rootId: number): TreeNode[] {
  const childrenByLeader = new Map<number, Subordinate[]>()

  for (const subordinate of subordinates) {
    const siblings = childrenByLeader.get(subordinate.leader_id) ?? []
    siblings.push(subordinate)
    childrenByLeader.set(subordinate.leader_id, siblings)
  }

  const visited = new Set<number>()

  const nodesUnder = (leaderId: number): TreeNode[] => {
    const nodes: TreeNode[] = []

    for (const subordinate of childrenByLeader.get(leaderId) ?? []) {
      if (visited.has(subordinate.id)) {
        continue
      }

      visited.add(subordinate.id)
      nodes.push({ subordinate, children: nodesUnder(subordinate.id) })
    }

    return nodes
  }

  return nodesUnder(rootId)
}

export function EmployeeTree({
  subordinates,
  leaderId,
}: {
  subordinates: Subordinate[]
  leaderId: number
}) {
  return <Branch nodes={buildTree(subordinates, leaderId)} level={0} />
}

function Branch({ nodes, level }: { nodes: TreeNode[]; level: number }) {
  // A indentação vive na lista, não na linha: é o que põe a guia vertical no recuo do nível.
  return (
    <ul className={level === 0 ? 'divide-y divide-slate-100' : 'ml-6 border-l border-slate-200'}>
      {nodes.map(({ subordinate, children }) => (
        <li key={subordinate.id}>
          <Link
            to={`/employees/${subordinate.id}`}
            className="flex flex-wrap items-center justify-between gap-3 px-6 py-3 transition-colors hover:bg-slate-50"
          >
            <div className="min-w-0">
              <p className="truncate font-medium">{subordinate.name}</p>
              <p className="truncate text-sm text-slate-500">{subordinate.position_name}</p>
            </div>

            <div className="flex items-center gap-3">
              <span className="text-xs text-slate-500">
                {subordinate.is_direct ? 'direto' : `nível ${subordinate.depth}`}
              </span>
              <ScoreBadge score={subordinate.latest_score} />
            </div>
          </Link>

          {children.length > 0 && <Branch nodes={children} level={level + 1} />}
        </li>
      ))}
    </ul>
  )
}
