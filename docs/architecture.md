# Arquitetura

## Visão geral (Docker Compose)

Três serviços na mesma rede; um único `docker compose up --build` sobe tudo.

```mermaid
flowchart LR
    B[Browser] -->|":3000"| W

    subgraph compose [docker compose]
        W["web · nginx<br/>SPA estática + proxy"]
        A["api · PHP 8.3<br/>Slim 4"]
        D[("db · PostgreSQL 16<br/>migrations via initdb")]

        W -->|"/api/* (resolver dinâmico)"| A
        A -->|"PDO, prepared statements"| D
    end
```

- O nginx serve o build do React e faz proxy de `/api` para o container `api` — o front só usa
  caminhos relativos e **não existe CORS** para configurar.
- O Postgres executa as migrations de `api/migrations/` na primeira subida (volume vazio) e
  provisiona também o banco `evaluation_test`, usado exclusivamente pela suíte de testes.
- Healthchecks encadeados: `web` só sobe com a `api` saudável, que só sobe com o `db` saudável —
  e o healthcheck do banco usa TCP para não reportar pronto durante o initdb.

## Fluxo de uma requisição na API

```mermaid
flowchart LR
    R[Route] --> M["CurrentEmployee<br/>Middleware"] --> C[Controller] --> S[Service] --> P[Repository] --> PDO[(PDO)]
```

| Camada | Responsabilidade | Não faz |
|---|---|---|
| `Middleware` | Resolve o líder atual do header `X-Employee-Id` | — |
| `Controller` | Lê a request, delega, serializa | Regra de negócio, SQL |
| `Service` | Hierarquia, visibilidade, limite semanal, validação | SQL |
| `Repository` | Único ponto que toca PDO; toda query parametrizada | Decisão de negócio |

O middleware é aplicado **por rota**: o catálogo (`/employees`, `/questions`) fica público para
alimentar o seletor de líder antes de haver líder escolhido.

## Sequência da regra mais rica: criar uma avaliação

```mermaid
sequenceDiagram
    participant F as Front
    participant M as Middleware
    participant S as EvaluationService
    participant H as HierarchyService
    participant R as EvaluationRepository
    participant DB as PostgreSQL

    F->>M: POST /api/evaluations (X-Employee-Id: 8)
    M->>M: valida header, carrega o avaliador
    M->>S: create(avaliador, payload)
    S->>S: valida as 6 respostas (1–4, sem faltar/sobrar/repetir)
    S->>H: assertCanAccess(avaliador, avaliado)
    H->>DB: CTE recursiva de descendentes
    DB-->>H: conjunto de subordinados
    alt avaliado fora da hierarquia
        H-->>F: 403 FORBIDDEN
    end
    S->>R: create(avaliador, avaliado, respostas)
    R->>DB: BEGIN · INSERT evaluation · 6× INSERT answer · COMMIT
    alt par já avaliado na semana ISO
        DB-->>R: 23505 no índice único semanal
        R-->>S: WeeklyLimitReachedException
        S-->>F: 409 WEEKLY_LIMIT_REACHED
    end
    R-->>S: id da avaliação
    S-->>F: 201 com nota (JOIN evaluation_score) e respostas
```

Dois pontos deliberados nessa sequência:

1. **A trava semanal é decidida pelo banco**, não por um `SELECT` antes do `INSERT` — duas
   requisições simultâneas do mesmo par são serializadas pelo índice único, e a segunda vira
   409. Não há janela de corrida.
2. **Validação antes da autorização**: a CTE recursiva é a consulta mais cara do sistema e não
   é executada para payloads que já nasceram inválidos.

## Frontend

```mermaid
flowchart TD
    subgraph shell [Shell]
        LS[LeaderSwitcher] --> CL["CurrentLeaderProvider<br/>(localStorage + contexto)"]
    end

    CL --> DA["Dashboard<br/>hierarquia + nota vigente"]
    DA --> ED["EmployeeDetail<br/>vigente + respostas + histórico"]
    ED --> EF["EvaluateForm<br/>6 questões, preview da nota"]

    DA & ED & EF --> Q["hooks/queries<br/>TanStack Query"]
    Q --> CLI["api/client<br/>único ponto que injeta X-Employee-Id"]
    CLI -->|"/api (proxy)"| API[API]
```

- O header `X-Employee-Id` é injetado em **um único lugar** (`api/client.ts`); nas rotas
  autenticadas a identidade é parâmetro obrigatório do tipo — esquecê-la é erro de compilação.
- O formulário de avaliação é *keyed* por `(líder, avaliado)`: trocar qualquer um dos dois
  remonta o formulário, e uma avaliação nunca é submetida em nome de um líder que não a
  preencheu.
- Erros da API chegam normalizados (`ApiError` com `status`/`code`); 409 e 403 têm tratamento
  visual próprio.

## Onde entraria autenticação real

O `CurrentEmployeeMiddleware` (backend) e o `CurrentLeaderProvider` + `api/client.ts` (front)
são os únicos pontos que conhecem a origem da identidade. Um auth real (sessão, JWT) substitui
o header confiado por credencial verificada **nesses dois pontos**; services, repositories,
regras de visibilidade e telas não mudam uma linha.
