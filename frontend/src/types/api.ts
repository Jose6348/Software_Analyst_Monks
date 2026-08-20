export interface Employee {
  id: number
  name: string
  email: string
  position_name: string
}

export interface Subordinate extends Employee {
  depth: number
  is_direct: boolean
  /** Decimal de escala fixa; a API o envia como string para não passar por um float. */
  latest_score: string | null
}
