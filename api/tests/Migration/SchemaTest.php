<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Tests\Support\DatabaseTestCase;

final class SchemaTest extends DatabaseTestCase
{
    private const HENRY = 8;
    private const DAVID = 4;
    private const JAMES = 10;

    public function testDumpSeedsTwentyEmployeesAndTheirHierarchy(): void
    {
        self::assertSame(20, $this->scalar('SELECT count(*) FROM employee'));
        self::assertSame(19, $this->scalar('SELECT count(*) FROM leader_lead'));

        // 20 funcionarios e 19 arestas com uma unica raiz: os testes de hierarquia contam com essa forma.
        self::assertSame(1, $this->scalar(
            'SELECT count(*) FROM employee e
              WHERE NOT EXISTS (SELECT 1 FROM leader_lead ll WHERE ll.lead_id = e.id)',
        ));
    }

    public function testQuestionSeedMatchesTheCaseWeights(): void
    {
        self::assertSame(6, $this->scalar('SELECT count(*) FROM question'));
        self::assertSame(100, $this->scalar('SELECT sum(weight) FROM question'));
    }

    public function testWeeklyIndexBlocksTheSamePairTwiceInOneIsoWeek(): void
    {
        $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');

        // Domingo 23/08 ainda pertence a semana ISO iniciada na segunda 17/08.
        $this->assertViolates(
            '23505',
            fn () => $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-23 23:00:00+00'),
        );
    }

    public function testWeeklyIndexAllowsTheSamePairInTheFollowingIsoWeek(): void
    {
        $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-24 09:00:00+00');

        self::assertSame(2, $this->countEvaluationsOf(self::JAMES));
    }

    public function testWeeklyLimitIsPerPairNotPerEvaluatedEmployee(): void
    {
        $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertEvaluation(self::DAVID, self::JAMES, '2026-08-18 10:00:00+00');

        self::assertSame(2, $this->countEvaluationsOf(self::JAMES));
    }

    public function testScoreViewAppliesTheWeightedFormula(): void
    {
        $evaluationId = $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertAnswers($evaluationId, [1 => 4, 2 => 3, 3 => 4, 4 => 2, 5 => 1, 6 => 3]);

        // 4*25 + 3*20 + 4*20 + 2*15 + 1*10 + 3*10 = 310 -> 310/100
        self::assertSame('3.10', $this->score($evaluationId));
    }

    public function testScoreViewCapsAtFour(): void
    {
        $evaluationId = $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertAnswers($evaluationId, [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4]);

        self::assertSame('4.00', $this->score($evaluationId));
    }

    public function testScoreViewDividesByTheFullQuestionnaireWeight(): void
    {
        $evaluationId = $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertAnswers($evaluationId, [1 => 4]);

        // Uma avaliacao incompleta sai baixa (100/100), nunca cheia sobre o peso respondido (100/25).
        self::assertSame('1.00', $this->score($evaluationId));
    }

    public function testAnswerOutsideTheOneToFourRangeIsRejected(): void
    {
        $evaluationId = $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');

        $this->assertViolates('23514', fn () => $this->insertAnswers($evaluationId, [1 => 5]));
        $this->assertViolates('23514', fn () => $this->insertAnswers($evaluationId, [1 => 0]));
    }

    public function testSelfEvaluationIsRejected(): void
    {
        $this->assertViolates(
            '23514',
            fn () => $this->insertEvaluation(self::JAMES, self::JAMES, '2026-08-18 09:00:00+00'),
        );
    }

    public function testTheSameQuestionCannotBeAnsweredTwice(): void
    {
        $evaluationId = $this->insertEvaluation(self::HENRY, self::JAMES, '2026-08-18 09:00:00+00');
        $this->insertAnswers($evaluationId, [1 => 4]);

        $this->assertViolates('23505', fn () => $this->insertAnswers($evaluationId, [1 => 3]));
    }

    private function insertEvaluation(int $evaluatorId, int $evaluatedId, string $createdAt): int
    {
        $statement = self::$pdo->prepare(
            'INSERT INTO evaluation (evaluator_id, evaluated_id, created_at)
             VALUES (:evaluator_id, :evaluated_id, :created_at)
             RETURNING id',
        );
        $statement->execute([
            'evaluator_id' => $evaluatorId,
            'evaluated_id' => $evaluatedId,
            'created_at'   => $createdAt,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<int,int> $answersByQuestionId */
    private function insertAnswers(int $evaluationId, array $answersByQuestionId): void
    {
        $statement = self::$pdo->prepare(
            'INSERT INTO evaluation_answer (evaluation_id, question_id, answer)
             VALUES (:evaluation_id, :question_id, :answer)',
        );

        foreach ($answersByQuestionId as $questionId => $answer) {
            $statement->execute([
                'evaluation_id' => $evaluationId,
                'question_id'   => $questionId,
                'answer'        => $answer,
            ]);
        }
    }

    private function score(int $evaluationId): string
    {
        $statement = self::$pdo->prepare('SELECT score FROM evaluation_score WHERE evaluation_id = :id');
        $statement->execute(['id' => $evaluationId]);
        $score = $statement->fetchColumn();

        // A view agrupa sobre evaluation_answer: sem respostas a avaliacao nao produz linha alguma.
        self::assertNotFalse($score, 'A avaliacao nao tem linha em evaluation_score.');

        return (string) $score;
    }

    private function countEvaluationsOf(int $evaluatedId): int
    {
        $statement = self::$pdo->prepare('SELECT count(*) FROM evaluation WHERE evaluated_id = :id');
        $statement->execute(['id' => $evaluatedId]);

        return (int) $statement->fetchColumn();
    }

    private function scalar(string $sql): int
    {
        return (int) self::$pdo->query($sql)->fetchColumn();
    }
}
