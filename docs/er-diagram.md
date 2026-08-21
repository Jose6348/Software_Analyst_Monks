# Modelo de dados

`employee` e `leader_lead` vêm do dump fornecido, sem alteração. As demais estruturas foram
criadas para o case.

```mermaid
erDiagram
    employee {
        serial id PK
        varchar name
        varchar email UK
        varchar position_name
    }

    leader_lead {
        int leader_id PK, FK
        int lead_id PK, FK
    }

    question {
        serial id PK
        varchar name UK
        smallint weight "CHECK > 0; os 6 pesos somam 100"
    }

    evaluation {
        serial id PK
        int evaluator_id FK
        int evaluated_id FK
        timestamptz created_at "trava semanal por par via indice unico"
    }

    evaluation_answer {
        int evaluation_id PK, FK
        int question_id PK, FK
        smallint answer "CHECK BETWEEN 1 AND 4"
    }

    employee ||--o{ leader_lead : "lidera (leader_id)"
    employee ||--o{ leader_lead : "e liderado (lead_id)"
    employee ||--o{ evaluation : "avalia (evaluator_id)"
    employee ||--o{ evaluation : "e avaliado (evaluated_id)"
    evaluation ||--|{ evaluation_answer : "tem 6"
    question ||--o{ evaluation_answer : "respondida em"
```

## Pontos que não aparecem no diagrama

**`leader_lead` é um grafo N:N, não uma árvore.** Nada no schema impede múltiplos líderes por
funcionário nem ciclos. As CTEs recursivas usam `UNION` (deduplica) e limite de profundidade;
a autorização não assume "ninguém é descendente de si mesmo".

**A trava semanal vive num índice, não numa coluna:**

```sql
CREATE UNIQUE INDEX uq_evaluation_pair_week
    ON evaluation (evaluator_id, evaluated_id, (date_trunc('week', created_at AT TIME ZONE 'UTC')));
```

Semana ISO (segunda a domingo), em UTC. O `AT TIME ZONE` torna a expressão `IMMUTABLE`, condição
do Postgres para indexá-la — e de quebra fixa a semântica da semana independente do fuso do
servidor.

**FKs de avaliação são `ON DELETE RESTRICT`.** Remover um funcionário não pode apagar histórico:
a imutabilidade das respostas é requisito do case.

## Views (a regra de negócio que mora no banco)

| View | O que resolve |
|---|---|
| `evaluation_score` | Nota ponderada: `ROUND(Σ(answer × weight) / Σ(weight), 2)`. Divisor é o peso total do questionário — avaliação incompleta sai com nota baixa, nunca inflada. |
| `employee_depth` | Profundidade de cada funcionário a partir da raiz (o CEO), via CTE recursiva. |
| `current_evaluation` | A avaliação **vigente** de cada funcionário: semana ISO mais recente → menor profundidade do avaliador → mais recente → maior id. |

As views existem para que cada regra tenha **um único dono**: a lista de subordinados e a tela
de detalhe leem `current_evaluation` e por construção não podem discordar sobre qual avaliação
vale.
