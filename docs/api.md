# API

Base: `http://localhost:8080/api`. Tudo JSON, UTF-8.

## Identificação

Não há login. O front guarda o `employee_id` do líder atual em `localStorage` e o envia no header
`X-Employee-Id`. As rotas de catálogo (`/employees`, `/questions`) dispensam o header — são elas
que alimentam o seletor de líder, antes de haver líder escolhido.

| Método | Rota | Header | Resumo |
|---|---|:---:|---|
| GET | `/health` | — | Sonda de disponibilidade |
| GET | `/employees` | — | Todos os funcionários |
| GET | `/questions` | — | As 6 questões e pesos |
| GET | `/me/subordinates` | sim | Subordinados diretos e indiretos, com a nota vigente |
| POST | `/evaluations` | sim | Registra uma avaliação |
| GET | `/employees/{id}/evaluations` | sim | Histórico do avaliado |
| GET | `/employees/{id}/evaluations/latest` | sim | Avaliação vigente + respostas |

## Erros

```json
{ "error": { "code": "FORBIDDEN", "message": "..." } }
```

| Status | `code` | Quando |
|---|---|---|
| 400 | `MISSING_EMPLOYEE_ID` | Header `X-Employee-Id` ausente |
| 400 | `INVALID_EMPLOYEE_ID` | Header não é um inteiro positivo |
| 400 | `VALIDATION_ERROR` | Payload inválido |
| 403 | `FORBIDDEN` | Alvo fora da hierarquia do usuário atual |
| 404 | `NOT_FOUND` | Funcionário, rota ou avaliação inexistente |
| 405 | `METHOD_NOT_ALLOWED` | Método não suportado pela rota |
| 409 | `WEEKLY_LIMIT_REACHED` | Par avaliador/avaliado já avaliado nesta semana |

---

## `GET /employees`

```bash
curl http://localhost:8080/api/employees
```

```json
[
  { "id": 1, "name": "Alice Hartman", "email": "alice.hartman@company.com", "position_name": "CEO" }
]
```

## `GET /questions`

```bash
curl http://localhost:8080/api/questions
```

```json
[
  { "id": 1, "name": "Entrega de Resultados", "weight": 25 },
  { "id": 2, "name": "Execução e Qualidade do Trabalho", "weight": 20 }
]
```

Os seis pesos somam 100.

## `GET /me/subordinates`

Diretos e indiretos, ordenados por profundidade e nome.

```bash
curl -H 'X-Employee-Id: 4' http://localhost:8080/api/me/subordinates
```

```json
[
  {
    "id": 8,
    "name": "Henry Patel",
    "email": "henry.patel@company.com",
    "position_name": "Senior Software Engineer",
    "depth": 1,
    "leader_id": 4,
    "is_direct": true,
    "latest_score": null
  },
  {
    "id": 10,
    "name": "James Watanabe",
    "email": "james.watanabe@company.com",
    "position_name": "Software Engineer",
    "depth": 2,
    "leader_id": 8,
    "is_direct": false,
    "latest_score": "1.00"
  }
]
```

`depth` é a distância até o líder atual; `is_direct` é o atalho para `depth === 1`. Quando um
funcionário é alcançável por mais de um caminho — `leader_lead` é um grafo N:N — vale a menor
profundidade.

`leader_id` é o líder imediato **no caminho mais curto**, e é o que permite ao front remontar a
árvore a partir da lista achatada. Para os diretos ele é o próprio líder atual.

`latest_score` é a nota da avaliação **vigente**, pela mesma regra de `/evaluations/latest`; é
`null` para quem nunca foi avaliado. Os dois endpoints leem a mesma definição e não podem
divergir.

## `POST /evaluations`

Exige as seis questões, cada uma respondida uma única vez com um inteiro de 1 a 4. Ou entram
todas, ou nenhuma.

```bash
curl -X POST http://localhost:8080/api/evaluations \
  -H 'Content-Type: application/json' \
  -H 'X-Employee-Id: 8' \
  -d '{
        "evaluated_id": 10,
        "answers": [
          {"question_id": 1, "answer": 4},
          {"question_id": 2, "answer": 3},
          {"question_id": 3, "answer": 4},
          {"question_id": 4, "answer": 2},
          {"question_id": 5, "answer": 1},
          {"question_id": 6, "answer": 3}
        ]
      }'
```

`201 Created`:

```json
{
  "id": 1,
  "evaluator": { "id": 8, "name": "Henry Patel", "email": "...", "position_name": "..." },
  "evaluated": { "id": 10, "name": "James Watanabe", "email": "...", "position_name": "..." },
  "created_at": "2026-08-19T17:58:09Z",
  "score": "3.10",
  "answers": [
    { "question_id": 1, "question_name": "Entrega de Resultados", "weight": 25, "answer": 4 }
  ]
}
```

Respostas de erro possíveis:

```json
{ "error": { "code": "VALIDATION_ERROR",
             "message": "A avaliação exige exatamente as 6 questões do questionário. Faltando: [6]. Desconhecidas: []." } }
```

```json
{ "error": { "code": "FORBIDDEN", "message": "Este funcionário não faz parte da sua hierarquia." } }
```

```json
{ "error": { "code": "WEEKLY_LIMIT_REACHED",
             "message": "Este avaliador já avaliou este funcionário nesta semana." } }
```

## `GET /employees/{id}/evaluations`

Histórico completo, do mais recente para o mais antigo. Sem as respostas — elas aparecem em
`/latest`. `is_current` marca qual entrada é a vigente pela regra da maior hierarquia.

```bash
curl -H 'X-Employee-Id: 4' http://localhost:8080/api/employees/10/evaluations
```

```json
[
  {
    "id": 2,
    "evaluator": { "id": 2, "name": "Bob Sinclair", "email": "...", "position_name": "CTO" },
    "created_at": "2026-08-19T18:20:03Z",
    "score": "1.00",
    "is_current": true
  },
  {
    "id": 1,
    "evaluator": { "id": 8, "name": "Henry Patel", "email": "...", "position_name": "..." },
    "created_at": "2026-08-19T18:20:02Z",
    "score": "4.00",
    "is_current": false
  }
]
```

Lista vazia (`200`) quando o funcionário nunca foi avaliado.

## `GET /employees/{id}/evaluations/latest`

A avaliação **vigente** com as respostas. O nome é "latest", mas não é simplesmente a linha mais
nova: dentro da semana ISO mais recente, vence o avaliador mais alto na hierarquia. No exemplo
acima, Bob (CTO) prevalece sobre Henry mesmo que ambos tenham avaliado no mesmo período.

```bash
curl -H 'X-Employee-Id: 4' http://localhost:8080/api/employees/10/evaluations/latest
```

Mesmo formato do `201` do `POST /evaluations`.

`404` quando o funcionário nunca foi avaliado:

```json
{ "error": { "code": "NOT_FOUND", "message": "Este funcionário ainda não foi avaliado." } }
```

---

## Visibilidade

As duas rotas de leitura exigem que o avaliado esteja no conjunto de descendentes de quem
consulta. Caso contrário, `403`.

| Quem olha | O que | Resultado |
|---|---|---|
| Alice (CEO) | avaliações de James | `200` — James é descendente indireto |
| Henry | avaliações de James | `200` — subordinado direto |
| James | as próprias | `403` |
| James | as de Karen (par) | `403` |
| James | as de Henry (superior) | `403` |
| Carol (outra árvore) | avaliações de James | `403` |

O filtro recai sobre o **avaliado**, não sobre quem avaliou: David vê a avaliação que Bob (chefe
de David) fez de James, porque James é subordinado de David.

## Notas de implementação

**A regra da maior hierarquia existe em um único lugar.** A view `current_evaluation` a define, e
tanto `/me/subordinates` quanto `/evaluations/latest` leem dela — não há como divergirem.

**Uma avaliação sem respostas nunca é eleita vigente.** A view exige que a avaliação tenha nota,
o que só acontece com respostas gravadas. A aplicação sempre grava as seis numa transação, mas a
restrição garante que uma linha incompleta vinda de fora não quebre `/latest`.

**Empates são resolvidos até o fim.** Semana, depois profundidade, depois `created_at`, depois o
id da avaliação. Linhas gravadas na mesma transação compartilham `now()`, então sem o último
critério o vencedor poderia variar entre requisições idênticas.

**Ids fora da faixa de `int4` devolvem 404**, não 500 — um id que a coluna não comporta não pode
identificar funcionário nenhum.
