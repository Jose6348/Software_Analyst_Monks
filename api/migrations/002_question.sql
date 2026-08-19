-- ============================================================
--  Questoes da avaliacao e seus pesos.
--  Fixas pelo enunciado do case; os pesos somam 100.
-- ============================================================

CREATE TABLE question (
    id     SERIAL       PRIMARY KEY,
    name   VARCHAR(100) NOT NULL UNIQUE,
    weight SMALLINT     NOT NULL CHECK (weight > 0)
);

INSERT INTO question (id, name, weight) VALUES
(1, 'Entrega de Resultados',                        25),
(2, 'Execução e Qualidade do Trabalho',             20),
(3, 'Capacidade de Aprendizado e Desenvolvimento',  20),
(4, 'Resolução de Problemas e Pensamento Crítico',  15),
(5, 'Colaboração, Influência e Liderança',          10),
(6, 'Visão Estratégica e Potencial de Crescimento', 10);

SELECT setval('question_id_seq', 6);
