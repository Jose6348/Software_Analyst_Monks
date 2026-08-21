# Histórias de usuário

Escritas antes do desenvolvimento, a partir do enunciado do case. Cada história carrega seus
critérios de aceite; juntas, elas definiram o escopo dos endpoints e das telas.

## Identificação

**US-01 — Trocar de líder sem login**
Como usuário da plataforma, quero escolher qual liderança estou representando, para simular o
acesso de diferentes líderes sem um sistema de login.

- O seletor no topo lista todos os funcionários (`GET /employees`).
- A escolha persiste em `localStorage` e sobrevive ao reload.
- Toda requisição autenticada envia o header `X-Employee-Id`.
- Trocar de líder recarrega os dados imediatamente — inclusive zerando um formulário de
  avaliação em preenchimento, que pertence ao líder anterior.

## Visualização

**US-02 — Ver minha hierarquia**
Como líder, quero ver todos os meus liderados, diretos e indiretos, para saber quem posso
avaliar.

- A lista traz diretos e indiretos, com a distinção visível (`is_direct`, profundidade).
- Cada liderado mostra a nota da avaliação vigente, ou "sem avaliação".
- Quem não lidera ninguém (ex.: James) vê a lista vazia — nunca um erro.

**US-03 — Ver a avaliação de um liderado**
Como líder, quero ver a avaliação mais recente de um liderado, com as respostas de cada
questão, para acompanhar seu desempenho.

- Mostra avaliador, data, nota ponderada e as 6 respostas com pesos.
- Se vários líderes avaliaram no período, vale a avaliação do líder mais alto na hierarquia
  (**maior hierarquia**; empate resolve pela mais recente).
- Um liderado nunca avaliado mostra o estado vazio com o botão de avaliar.

**US-04 — Ver o histórico de um liderado**
Como líder, quero ver o histórico de avaliações de um liderado, para acompanhar a evolução.

- Lista do mais recente ao mais antigo: avaliador, data e nota.
- A avaliação vigente é marcada visualmente.

**US-05 — Visibilidade restrita (segurança)**
Como funcionário, **não** posso ver minha própria avaliação, nem a de pares ou superiores.

- Toda rota de leitura valida que o avaliado pertence aos meus descendentes; senão, 403.
- James (folha da árvore) não vê avaliação de ninguém — nem a própria.
- A regra é do backend; a UI apenas apresenta o erro.

## Avaliação

**US-06 — Avaliar um liderado**
Como líder, quero avaliar qualquer funcionário da minha hierarquia respondendo às 6 questões,
para registrar o desempenho dele.

- 6 questões fixas, resposta inteira de 1 a 4 cada, todas obrigatórias.
- Preview da nota ponderada antes do envio, com a mesma fórmula do backend.
- Alvo fora da minha hierarquia é bloqueado antes do preenchimento (e pelo backend, com 403).

**US-07 — Limite semanal por par**
Como sistema, permito no máximo 1 avaliação por semana ISO para cada par (avaliador, avaliado),
para evitar avaliações repetidas.

- A trava é por par: David pode avaliar James mesmo que Henry já o tenha avaliado na semana.
- Violação retorna 409 com mensagem explicando quando será possível avaliar de novo.
- Enforcement no banco (índice único), imune a corrida entre requisições simultâneas.

**US-08 — Imutabilidade**
Como sistema, garanto que respostas enviadas nunca sejam alteradas.

- Não existem endpoints de edição ou exclusão de avaliações.
- O formulário avisa a irreversibilidade antes do envio.
