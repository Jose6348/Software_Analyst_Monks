import { Link } from 'react-router-dom'

import { ScoreBadge } from './ScoreBadge'
import type { Subordinate } from '../types/api'

interface TreeNode {
  subordinate: Subordinate
  children: TreeNode[]
}

/**
 * Remonta a árvore a partir da lista achatada, usando o `leader_id` que a CTE devolve.
 *
 * O visitado não é zelo excessivo: `leader_lead` é um grafo N:N e admite ciclos. Com
 * Henry → James → Henry, o pai do Henry seria o James e o do James seria o Henry, e a
 * recursão não terminaria.
 */
function buildTree(subordinates: Subordinate[], rootId: number): TreeNode[] {
  const childrenByLeader = new Map<number, Subordinate[]>()

  for (const subordinate of subordinates) {
    const siblings = childrenByLeader.get(subordinate.leader_id) ?? []
    siblings.push(subordinate)
    childrenByLeader.set(subordinate.leader_id, siblings)
  }

  const visited = new Set<number>()

  const nodesUnder = (leaderId: number): TreeNode[] =>
    (childrenByLeader.get(leaderId) ?? [])
      .filter((subordinate) => !visited.has(subordinate.id))
      .map((subordinate) => {
        visited.add(subordinate.id)

        return { subordinate, children: nodesUnder(subordinate.id) }
      })

  const tree = nodesUnder(rootId)

  // Quem não foi alcançado a partir da raiz sobe para o topo: melhor exibir fora de posição
  // do que sumir da tela.
  const orphans = subordinates
    .filter((subordinate) => !visited.has(subordinate.id))
    .map((subordinate) => ({ subordinate, children: [] }))

  return [...tree, ...orphans]
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
  return (
    <ul className={level === 0 ? 'divide-y divide-slate-100' : 'border-l border-slate-200'}>
      {nodes.map(({ subordinate, children }) => (
        <li key={subordinate.id}>
          <Link
            to={`/employees/${subordinate.id}`}
            className="flex flex-wrap items-center justify-between gap-3 py-3 pr-6 transition-colors hover:bg-slate-50"
            style={{ paddingLeft: `${1.5 + level * 1.5}rem` }}
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
