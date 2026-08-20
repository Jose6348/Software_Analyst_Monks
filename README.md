# Plataforma de Avaliação de Liderados

Aplicação web onde um líder avalia os funcionários da sua hierarquia — diretos e indiretos —
respondendo seis questões com peso, sob regras estritas de periodicidade, imutabilidade e
visibilidade.

**Stack:** PHP 8.3 + Slim 4 + PDO · PostgreSQL 16 · React 19 + TypeScript + Vite · Docker Compose.

---

## Como rodar

Pré-requisito único: **Docker** com Compose v2+ (`docker compose version`).

```bash
git clone https://github.com/Jose6348/Software_Analyst_Monks.git
cd Software_Analyst_Monks
docker compose up --build
```

Isso é tudo — não há chave, credencial externa ou passo manual de migration.

| Serviço | URL |
|---|---|
| Front (nginx) | http://localhost:3000 |
| API | http://localhost:8080/api |
| Postgres | `localhost:5433` |

Verifique que subiu:

```bash
curl http://localhost:8080/api/health
# {"status":"ok"}
```

### Variáveis de ambiente

Todas têm padrão embutido, então o comando acima funciona sem configuração. Para sobrescrever,
copie o exemplo e ajuste:

```bash
cp .env.example .env
```

| Variável | Padrão | Para que serve |
|---|---|---|
| `DB_NAME` | `evaluation` | Nome do banco |
| `DB_USER` | `app` | Usuário do Postgres |
| `DB_PASSWORD` | `app` | Senha do Postgres |
| `DB_HOST_PORT` | `5433` | Porta do Postgres no host (5433 evita colisão com um Postgres local em 5432) |
| `API_HOST_PORT` | `8080` | Porta da API no host |
| `WEB_HOST_PORT` | `3000` | Porta do front no host |

O serviço `api` recebe `DB_HOST`/`DB_PORT` apontando para o container `db` na rede interna do
Compose; não há nada a configurar aí.

### Recriar o banco do zero

As migrations rodam via `docker-entrypoint-initdb.d`, que o Postgres executa **uma única vez**,
em volume vazio. Depois de alterar qualquer arquivo em `api/migrations/`:

```bash
docker compose down -v && docker compose up --build
```

O `-v` é obrigatório — sem ele o volume antigo persiste e as migrations não reexecutam.

### Testes

```bash
docker compose exec api composer test
```

São de integração por decisão consciente: as regras críticas (CTEs recursivas, índice único
semanal, fórmula da nota) vivem em SQL, e testá-las com PDO mockado não testaria nada.

A suíte usa um **banco separado**, `evaluation_test`, criado pelo mesmo `initdb` a partir das
mesmas migrations. Os testes de HTTP truncam as tabelas de avaliação entre os casos, e isso
jamais pode alcançar os dados da aplicação — rodar a suíte depois de uma demonstração não pode
apagar o que foi criado nela.

### Front em modo dev (fora do Docker)

```bash
docker compose up db api
cd frontend && npm install && npm run dev
```

O Vite faz proxy de `/api` para `localhost:8080`, espelhando o que o nginx faz em produção. Assim
o front sempre usa caminhos relativos e a API não precisa de CORS.

---

## Arquitetura

```
Browser ──► nginx (web:80) ──► /api/* ──► Slim (api:8080) ──► PostgreSQL (db:5432)
              └─ SPA estática
```

Uma requisição na API percorre sempre a mesma cadeia:

```
Route → CurrentEmployeeMiddleware → Controller → Service → Repository → PDO
```

| Camada | Responsabilidade | Não faz |
|---|---|---|
| `Controller` | Lê a request, delega, serializa a resposta | Regra de negócio, SQL |
| `Service` | Hierarquia, visibilidade, limite semanal, validação do payload | SQL |
| `Repository` | Único ponto que toca PDO; toda query parametrizada | Decisão de negócio |
| `Middleware` | Resolve o líder atual a partir de `X-Employee-Id` | — |

```
api/
├── public/index.php       front controller
├── src/
│   ├── Bootstrap/         container, rotas, conexão PDO
│   ├── Controller/
│   ├── Service/           HierarchyService, EvaluationService
│   ├── Repository/
│   ├── Model/             DTOs readonly
│   ├── Middleware/
│   └── Http/              ApiException, ErrorHandler, JsonResponse
├── migrations/            executadas em ordem pelo initdb do Postgres
└── tests/
```

### Modelo de dados

`employee` e `leader_lead` vêm do dump fornecido, sem alteração. `leader_lead` é uma relação N:N
auto-referenciada (`leader_id → lead_id`) — um grafo, não necessariamente uma árvore.

| Tabela | Colunas |
|---|---|
| `employee` | `id`, `name`, `email`, `position_name` |
| `leader_lead` | `leader_id`, `lead_id` |
| `question` | `id`, `name`, `weight` — seed com as 6 questões, pesos somando 100 |
| `evaluation` | `id`, `evaluator_id`, `evaluated_id`, `created_at` |
| `evaluation_answer` | `evaluation_id`, `question_id`, `answer` (1–4) |
| `evaluation_score` | *view*: nota ponderada por avaliação |
| `employee_depth` | *view*: profundidade de cada funcionário a partir da raiz |
| `current_evaluation` | *view*: a avaliação vigente de cada funcionário |

Não existe coluna de nota armazenada. A view `evaluation_score` concentra a fórmula em um único
lugar:

```sql
ROUND(SUM(answer * weight)::numeric / (SELECT SUM(weight) FROM question), 2)
```

O divisor é o peso total do questionário — hoje 100, exatamente o `/100` do enunciado, mas
derivado em vez de fixo. Dividir pela soma dos pesos *respondidos* seria diferente e pior: uma
avaliação incompleta sairia com nota cheia sobre um subconjunto de questões. Do jeito atual ela
sai baixa, que é o modo seguro de falhar.

A regra da maior hierarquia segue o mesmo princípio: em vez de repetir o critério em cada query,
a view `current_evaluation` o define uma vez. A lista de subordinados e a tela de detalhe leem
dela, então não têm como discordar sobre qual avaliação vale.

### Endpoints

Todas as rotas ficam sob `/api` e respondem JSON. Só as marcadas exigem o header
`X-Employee-Id`; a lista de funcionários é pública justamente para alimentar o seletor de líder
antes de haver um líder escolhido.

| Método | Rota | `X-Employee-Id` | Descrição |
|---|---|:---:|---|
| GET | `/health` | — | Sonda de disponibilidade |
| GET | `/employees` | — | Todos os funcionários, para o seletor de líder |
| GET | `/questions` | — | As 6 questões e seus pesos |
| GET | `/me/subordinates` | sim | Subordinados diretos e indiretos do líder atual |
| POST | `/evaluations` | sim | Registra uma avaliação |
| GET | `/employees/{id}/evaluations` | sim | Histórico do avaliado |
| GET | `/employees/{id}/evaluations/latest` | sim | Avaliação vigente + respostas |

Exemplos de request e response de cada rota estão em [`docs/api.md`](docs/api.md).

```bash
curl -H 'X-Employee-Id: 8' http://localhost:8080/api/me/subordinates
```

```json
[
  {
    "id": 10,
    "name": "James Watanabe",
    "email": "james.watanabe@company.com",
    "position_name": "Software Engineer",
    "depth": 1,
    "is_direct": true
  }
]
```

`depth` é a distância até o líder atual e `is_direct` é o atalho para `depth === 1`. Quando um
funcionário é alcançável por mais de um caminho — `leader_lead` é um grafo N:N, não uma árvore —
vale a menor profundidade.

#### `POST /api/evaluations`

Exige as seis questões, cada uma respondida uma única vez com um inteiro de 1 a 4. Ou entram
todas, ou nenhuma.

```bash
curl -X POST http://localhost:8080/api/evaluations   -H 'Content-Type: application/json'   -H 'X-Employee-Id: 8'   -d '{
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

A nota vem como **string**, não como número: é um decimal de escala fixa, e passá-lo por um
float JSON é justamente o que produz `3.1000000000000001`. O front só exibe.

### Erros

Todas as respostas de erro têm o mesmo formato:

```json
{ "error": { "code": "FORBIDDEN", "message": "..." } }
```

| Status | `code` | Quando |
|---|---|---|
| 400 | `MISSING_EMPLOYEE_ID` | Header `X-Employee-Id` ausente |
| 400 | `INVALID_EMPLOYEE_ID` | Header `X-Employee-Id` não é um inteiro positivo |
| 400 | `VALIDATION_ERROR` | Payload inválido |
| 403 | `FORBIDDEN` | Avaliado fora da hierarquia do usuário atual |
| 404 | `NOT_FOUND` | Recurso inexistente |
| 409 | `WEEKLY_LIMIT_REACHED` | Par avaliador/avaliado já avaliado nesta semana |

O limite semanal é detectado pelo banco (violação do índice único), traduzido para o conceito de
negócio no `EvaluationRepository` e mapeado para 409 no `EvaluationService` — a regra e sua
resposta HTTP ficam na camada de serviço, o SQLSTATE não sai da camada de persistência.

---

## Decisões e premissas

**Semana ISO, em UTC.** O limite de uma avaliação por semana usa a semana ISO (segunda a
domingo), avaliada em UTC. A regra é garantida pelo banco, não pela aplicação:

```sql
CREATE UNIQUE INDEX uq_evaluation_pair_week
    ON evaluation (evaluator_id, evaluated_id, (date_trunc('week', created_at AT TIME ZONE 'UTC')));
```

O `AT TIME ZONE 'UTC'` não é decoração: `date_trunc(text, timestamptz)` é `STABLE` porque depende
do fuso da sessão, e o Postgres exige expressões `IMMUTABLE` em índices. Fixar o fuso resolve e
ainda torna a semana determinística independente de onde o servidor roda. Uma violação vira HTTP
409 — a corrida entre duas requisições simultâneas é resolvida pelo banco, não por um `SELECT`
antes do `INSERT`.

**"Respeitando sempre a maior hierarquia" resolvido em três critérios.** O enunciado pede a
avaliação *mais recente* **e** o respeito à hierarquia, que se contradizem quando um líder alto
avaliou há muito tempo e o chefe direto avaliou ontem. A ordem adotada:

1. recorta a **semana ISO mais recente** em que o funcionário foi avaliado;
2. dentro dela, vence o avaliador de **menor profundidade a partir da raiz** (o CEO);
3. empate de profundidade resolve pela avaliação **mais recente**.

Sem o passo 1, uma avaliação antiga do CEO venceria para sempre uma recente do chefe direto.
Profundidade é contada **a partir da raiz**, não a partir de quem consulta — é o que "maior
hierarquia" significa, e o resultado tem que ser o mesmo para qualquer observador.

**O limite é por par, não por avaliado.** Henry avaliar James não impede David (chefe de Henry)
de avaliar James na mesma semana. É a leitura literal do enunciado e está coberta por teste.

**Imutabilidade.** Não existe endpoint de `PUT`, `PATCH` ou `DELETE` de avaliação. A ausência é a
garantia.

**Visibilidade recai sobre o avaliado.** O usuário enxerga as avaliações de quem está no seu
conjunto de descendentes, independente de quem avaliou. Consequência intencional: David vê a
avaliação que Bob (chefe de David) fez de James, porque James é subordinado de David. O que fica
vedado é ver avaliações de si mesmo, de pares e de superiores.

**Auto-avaliação é barrada explicitamente.** Seria tentador confiar em "ninguém é descendente de
si mesmo", mas `leader_lead` admite ciclos e nesse caso a premissa é falsa — a travessia
devolveria o próprio usuário. A autorização compara os ids diretamente, e o banco reforça com
`CHECK (evaluator_id <> evaluated_id)`.

**Identificação do líder sem login.** O case veda um sistema de login completo. O front guarda o
`employee_id` do líder atual em `localStorage` e o envia em toda requisição no header
`X-Employee-Id`; um seletor no topo da UI troca de líder. Isso **simula** autenticação e não é
seguro — qualquer cliente pode forjar o header. Em produção o `CurrentEmployeeMiddleware` seria o
único ponto de troca: em vez de confiar num header, validaria um JWT ou uma sessão e extrairia
dali o `employee_id`. Todo o resto — services, repositories, regras de visibilidade — continuaria
idêntico, porque nada abaixo do middleware sabe de onde veio a identidade.

**PostgreSQL.** O dump fornecido já usa sintaxe Postgres (`SERIAL`, `setval`). Além disso o
problema pede exatamente aquilo que o Postgres faz bem: CTE recursiva para a hierarquia, índice
único por expressão para a trava semanal e `DISTINCT ON` para escolher uma linha por grupo.

**Proteção contra ciclos.** `leader_lead` é um grafo N:N e nada no schema impede um ciclo. As CTEs
recursivas usam `UNION` (que deduplica) e limite de profundidade, então um ciclo eventual não
trava a query. Pelo mesmo motivo a autorização não se apoia em "ninguém é descendente de si
mesmo": há uma checagem explícita, porque num ciclo essa premissa é falsa.

**Documentação complementar.** [`docs/api.md`](docs/api.md) traz request e response de exemplo de
cada endpoint, além da matriz de visibilidade.
