<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Tests\Support\TransactionalTestCase;

/**
 * A regra "respeitando sempre a maior hierarquia", exercitada direto no SQL para poder controlar
 * datas e avaliadores que a API sozinha não permitiria produzir.
 */
final class EvaluationRankingTest extends TransactionalTestCase
{
    private const ALICE = 1;
    private const BOB = 2;
    private const DAVID = 4;
    private const EVA = 5;
    private const HENRY = 8;
    private const JAMES = 10;

    private const THIS_WEEK = '2026-08-18 09:00:00+00';
    private const LAST_WEEK = '2026-08-11 09:00:00+00';

    public function testDepthIsCountedFromTheRootNotFromTheViewer(): void
    {
        $depths = $this->depths();

        self::assertSame(0, $depths[self::ALICE]);
        self::assertSame(1, $depths[self::BOB]);
        self::assertSame(2, $depths[self::DAVID]);
        self::assertSame(3, $depths[self::HENRY]);
        self::assertSame(4, $depths[self::JAMES]);
    }

    public function testEverySeededEmployeeHasADepth(): void
    {
        self::assertCount(20, $this->depths());
    }

    public function testTheHighestEvaluatorInTheHierarchyWins(): void
    {
        $henrys = $this->evaluate(self::HENRY, self::JAMES, self::THIS_WEEK);
        $bobs = $this->evaluate(self::BOB, self::JAMES, self::THIS_WEEK);

        // Bob esta mais perto da raiz (1) que Henry (3): dentro da semana, quem decide e a hierarquia.
        self::assertSame($bobs, $this->currentIdFor(self::JAMES));
        self::assertNotSame($henrys, $this->currentIdFor(self::JAMES));
    }

    public function testOrderOfArrivalDoesNotOverrideHierarchy(): void
    {
        $bobs = $this->evaluate(self::BOB, self::JAMES, self::THIS_WEEK);
        $this->evaluate(self::HENRY, self::JAMES, '2026-08-19 23:00:00+00');

        self::assertSame($bobs, $this->currentIdFor(self::JAMES));
    }

    public function testTiesOnDepthAreBrokenByTheMostRecent(): void
    {
        // David e Eva estao ambos na profundidade 2.
        $this->evaluate(self::DAVID, self::JAMES, '2026-08-18 09:00:00+00');
        $evas = $this->evaluate(self::EVA, self::JAMES, '2026-08-18 15:00:00+00');

        self::assertSame($evas, $this->currentIdFor(self::JAMES));
    }

    public function testAnOlderEvaluationNeverBeatsAMoreRecentWeek(): void
    {
        // Sem o recorte por semana, a avaliacao da Alice (profundidade 0) venceria para sempre.
        $this->evaluate(self::ALICE, self::JAMES, self::LAST_WEEK);
        $henrys = $this->evaluate(self::HENRY, self::JAMES, self::THIS_WEEK);

        self::assertSame($henrys, $this->currentIdFor(self::JAMES));
    }

    public function testWithinTheLatestWeekHierarchyStillDecides(): void
    {
        $this->evaluate(self::ALICE, self::JAMES, self::LAST_WEEK);
        $this->evaluate(self::HENRY, self::JAMES, self::THIS_WEEK);
        $bobs = $this->evaluate(self::BOB, self::JAMES, self::THIS_WEEK);

        self::assertSame($bobs, $this->currentIdFor(self::JAMES));
    }

    public function testTiesOnTimestampAreBrokenDeterministicallyByIdentity(): void
    {
        // Avaliacoes gravadas na mesma transacao compartilham now(); sem desempate por id o
        // vencedor variaria entre execucoes.
        $davids = $this->evaluate(self::DAVID, self::JAMES, self::THIS_WEEK);
        $evas = $this->evaluate(self::EVA, self::JAMES, self::THIS_WEEK);

        self::assertSame(max($davids, $evas), $this->currentIdFor(self::JAMES));
    }

    public function testAnEvaluationWithoutAnswersIsNeverElectedCurrent(): void
    {
        $complete = $this->evaluate(self::HENRY, self::JAMES, self::THIS_WEEK);
        $this->insertEvaluationRow(self::BOB, self::JAMES, self::THIS_WEEK);

        // Bob esta mais alto na hierarquia, mas a avaliacao dele nao tem respostas — logo nao tem
        // nota, e eleger essa avaliacao quebraria /latest.
        self::assertSame($complete, $this->currentIdFor(self::JAMES));
    }

    public function testAnEvaluatorOutsideAnyTreeDoesNotDisappearFromTheRanking(): void
    {
        // Par ciclico desligado da arvore da Alice: nenhum dos dois e "sem lider", entao nenhum
        // recebe profundidade. A avaliacao existe e nao pode sumir do ranking.
        [$leader, $lead] = $this->createDetachedCycle();

        $orphan = $this->evaluate($leader, $lead, self::THIS_WEEK);

        self::assertNull($this->depths()[$leader] ?? null, 'o avaliador nao tem profundidade');
        self::assertSame($orphan, $this->currentIdFor($lead));
    }

    public function testAnEmployeeNeverEvaluatedHasNoCurrentEvaluation(): void
    {
        self::assertNull($this->currentIdFor(self::JAMES));
    }

    public function testEachEmployeeHasAtMostOneCurrentEvaluation(): void
    {
        $this->evaluate(self::HENRY, self::JAMES, self::THIS_WEEK);
        $this->evaluate(self::BOB, self::JAMES, self::THIS_WEEK);
        $this->evaluate(self::ALICE, self::JAMES, self::LAST_WEEK);

        $statement = self::$pdo->prepare(
            'SELECT count(*) FROM current_evaluation WHERE evaluated_id = :id',
        );
        $statement->execute(['id' => self::JAMES]);

        self::assertSame(1, (int) $statement->fetchColumn());
    }

    private function insertEvaluationRow(int $evaluatorId, int $evaluatedId, string $createdAt): int
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

    private function evaluate(int $evaluatorId, int $evaluatedId, string $createdAt): int
    {
        $evaluationId = $this->insertEvaluationRow($evaluatorId, $evaluatedId, $createdAt);

        $answer = self::$pdo->prepare(
            'INSERT INTO evaluation_answer (evaluation_id, question_id, answer)
             VALUES (:evaluation_id, :question_id, 3)',
        );

        foreach (range(1, 6) as $questionId) {
            $answer->execute(['evaluation_id' => $evaluationId, 'question_id' => $questionId]);
        }

        return $evaluationId;
    }

    private function currentIdFor(int $evaluatedId): ?int
    {
        $statement = self::$pdo->prepare(
            'SELECT evaluation_id FROM current_evaluation WHERE evaluated_id = :id',
        );
        $statement->execute(['id' => $evaluatedId]);
        $evaluationId = $statement->fetchColumn();

        return $evaluationId === false ? null : (int) $evaluationId;
    }

    /** @return array{int,int} */
    private function createDetachedCycle(): array
    {
        $insert = self::$pdo->prepare(
            'INSERT INTO employee (name, email, position_name)
             VALUES (:name, :email, :position_name)
             RETURNING id',
        );

        $ids = [];

        foreach (['Orphan One', 'Orphan Two'] as $index => $name) {
            $insert->execute([
                'name'          => $name,
                'email'         => sprintf('orphan%d@company.com', $index),
                'position_name' => 'Detached',
            ]);
            $ids[] = (int) $insert->fetchColumn();
        }

        $link = self::$pdo->prepare(
            'INSERT INTO leader_lead (leader_id, lead_id) VALUES (:leader_id, :lead_id)',
        );
        $link->execute(['leader_id' => $ids[0], 'lead_id' => $ids[1]]);
        $link->execute(['leader_id' => $ids[1], 'lead_id' => $ids[0]]);

        return [$ids[0], $ids[1]];
    }

    /** @return array<int,int> */
    private function depths(): array
    {
        $statement = self::$pdo->prepare('SELECT employee_id, depth FROM employee_depth');
        $statement->execute();

        $depths = [];

        foreach ($statement->fetchAll() as $row) {
            $depths[(int) $row['employee_id']] = (int) $row['depth'];
        }

        return $depths;
    }
}
