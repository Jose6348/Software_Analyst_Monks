export interface Employee {
  id: number
  name: string
  email: string
  position_name: string
}

export interface Subordinate extends Employee {
  depth: number
  /** Líder imediato no caminho mais curto; é o que permite remontar a árvore. */
  leader_id: number
  is_direct: boolean
  /** Decimal de escala fixa; a API o envia como string para não passar por um float. */
  latest_score: string | null
}

export interface Question {
  id: number
  name: string
  weight: number
}

export interface EvaluationAnswer {
  question_id: number
  question_name: string
  weight: number
  answer: number
}

export interface Evaluation {
  id: number
  evaluator: Employee
  evaluated: Employee
  created_at: string
  score: string
  answers: EvaluationAnswer[]
}

export type EvaluationSummary = Pick<Evaluation, 'id' | 'evaluator' | 'created_at' | 'score'> & {
  is_current: boolean
}

export interface NewEvaluation {
  evaluated_id: number
  answers: Array<Pick<EvaluationAnswer, 'question_id' | 'answer'>>
}
