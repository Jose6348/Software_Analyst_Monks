-- ============================================================
--  Avaliacoes e respostas.
--  Respostas sao imutaveis: nao existe UPDATE/DELETE na aplicacao.
-- ============================================================

CREATE TABLE evaluation (
    id           SERIAL      PRIMARY KEY,
    -- RESTRICT, nao CASCADE: remover um funcionario nao pode apagar avaliacoes ja registradas.
    evaluator_id INT         NOT NULL REFERENCES employee(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    evaluated_id INT         NOT NULL REFERENCES employee(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_no_self_evaluation CHECK (evaluator_id <> evaluated_id)
);

-- Uma avaliacao por semana ISO (segunda a domingo, UTC) para cada par avaliador/avaliado.
-- `date_trunc(text, timestamptz)` e STABLE; fixar o fuso com AT TIME ZONE torna a expressao
-- IMMUTABLE, requisito do Postgres para indices por expressao.
CREATE UNIQUE INDEX uq_evaluation_pair_week
    ON evaluation (evaluator_id, evaluated_id, (date_trunc('week', created_at AT TIME ZONE 'UTC')));

CREATE INDEX idx_evaluation_evaluated ON evaluation (evaluated_id, created_at DESC);

CREATE TABLE evaluation_answer (
    evaluation_id INT      NOT NULL REFERENCES evaluation(id) ON DELETE CASCADE ON UPDATE CASCADE,
    question_id   INT      NOT NULL REFERENCES question(id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    answer        SMALLINT NOT NULL CHECK (answer BETWEEN 1 AND 4),
    PRIMARY KEY (evaluation_id, question_id)
);

-- Nota ponderada com uma unica definicao, sem coluna redundante.
-- O divisor e o peso total do questionario (100), nao a soma dos pesos respondidos: uma avaliacao
-- incompleta tem que sair com nota baixa, nao com nota cheia sobre um subconjunto de questoes.
CREATE VIEW evaluation_score AS
SELECT ea.evaluation_id,
       ROUND(SUM(ea.answer * q.weight)::numeric / (SELECT SUM(weight) FROM question), 2) AS score
  FROM evaluation_answer ea
  JOIN question q ON q.id = ea.question_id
 GROUP BY ea.evaluation_id;
