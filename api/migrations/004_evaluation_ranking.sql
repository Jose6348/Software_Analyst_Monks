-- ============================================================
--  "Respeitando sempre a maior hierarquia".
--  Duas views para que a regra exista em um unico lugar: a lista de subordinados e a tela de
--  detalhe consultam a mesma definicao e nao podem discordar sobre qual avaliacao vale.
-- ============================================================

-- Profundidade de cada funcionario a partir da raiz (o CEO, quem nao tem lider).
-- Diferente da travessia de descendentes, esta nao depende de parametro, entao cabe em uma view.
-- UNION deduplica e o limite de profundidade corta ciclos; MIN porque leader_lead e um grafo
-- N:N e um funcionario pode ser alcancado por mais de um caminho.
CREATE VIEW employee_depth AS
WITH RECURSIVE tree AS (
    SELECT e.id AS employee_id, 0 AS depth
      FROM employee e
     WHERE NOT EXISTS (SELECT 1 FROM leader_lead ll WHERE ll.lead_id = e.id)
    UNION
    SELECT ll.lead_id, t.depth + 1
      FROM leader_lead ll
      JOIN tree t ON ll.leader_id = t.employee_id
     WHERE t.depth < 20
)
SELECT employee_id, MIN(depth) AS depth
  FROM tree
 GROUP BY employee_id;

-- A avaliacao vigente de cada funcionario, em tres criterios:
--   1. a semana ISO mais recente em que ele foi avaliado;
--   2. dentro dela, o avaliador mais alto na hierarquia (menor profundidade a partir da raiz);
--   3. empate de profundidade resolve pela avaliacao mais recente.
-- O recorte por semana vem antes da hierarquia de proposito: sem ele uma avaliacao antiga do CEO
-- venceria para sempre uma recente do chefe direto.
-- O JOIN com evaluation_score e proposital: uma avaliacao sem respostas nao produz nota e nao
-- pode ser eleita vigente, senao /latest quebraria ao tentar ler a nota dela.
-- O LEFT JOIN com employee_depth tambem: um avaliador fora de qualquer arvore (so possivel num
-- ciclo sem raiz) fica sem profundidade e vai para o fim da ordem, em vez de sumir do ranking e
-- fazer os endpoints discordarem entre si.
CREATE VIEW current_evaluation AS
SELECT DISTINCT ON (ev.evaluated_id)
       ev.evaluated_id,
       ev.id AS evaluation_id
  FROM evaluation ev
  JOIN evaluation_score es ON es.evaluation_id = ev.id
  LEFT JOIN employee_depth d ON d.employee_id = ev.evaluator_id
 ORDER BY ev.evaluated_id,
          date_trunc('week', ev.created_at AT TIME ZONE 'UTC') DESC,
          d.depth ASC NULLS LAST,
          ev.created_at DESC,
          ev.id DESC;
