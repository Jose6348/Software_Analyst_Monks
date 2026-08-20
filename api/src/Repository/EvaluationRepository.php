<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Employee;
use App\Model\Evaluation;
use App\Model\EvaluationAnswer;
use App\Model\EvaluationSummary;
use PDO;
use PDOException;
use Throwable;

final readonly class EvaluationRepository
{
    private const UNIQUE_VIOLATION = '23505';
    private const WEEKLY_INDEX = 'uq_evaluation_pair_week';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Grava a avaliacao e suas respostas atomicamente: ou entram todas, ou nenhuma.
     *
     * @param array<int,int> $answersByQuestionId
     * @throws WeeklyLimitReachedException quando o indice unico recusa o par nesta semana
     */
    public function create(int $evaluatorId, int $evaluatedId, array $answersByQuestionId): int
    {
        $this->pdo->beginTransaction();

        try {
            $evaluation = $this->pdo->prepare(
                'INSERT INTO evaluation (evaluator_id, evaluated_id)
                 VALUES (:evaluator_id, :evaluated_id)
                 RETURNING id',
            );
            $evaluation->execute([
                'evaluator_id' => $evaluatorId,
                'evaluated_id' => $evaluatedId,
            ]);
            $evaluationId = (int) $evaluation->fetchColumn();

            $answer = $this->pdo->prepare(
                'INSERT INTO evaluation_answer (evaluation_id, question_id, answer)
                 VALUES (:evaluation_id, :question_id, :answer)',
            );

            foreach ($answersByQuestionId as $questionId => $value) {
                $answer->execute([
                    'evaluation_id' => $evaluationId,
                    'question_id'   => $questionId,
                    'answer'        => $value,
                ]);
            }

            $this->pdo->commit();

            return $evaluationId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $this->translate($exception);
        }
    }

    public function findById(int $id): ?Evaluation
    {
        $statement = $this->pdo->prepare(
            'SELECT ev.id,
                    to_char(ev.created_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') AS created_at,
                    es.score,
                    er.id AS evaluator_id,
                    er.name AS evaluator_name,
                    er.email AS evaluator_email,
                    er.position_name AS evaluator_position_name,
                    ed.id AS evaluated_id,
                    ed.name AS evaluated_name,
                    ed.email AS evaluated_email,
                    ed.position_name AS evaluated_position_name
               FROM evaluation ev
               JOIN evaluation_score es ON es.evaluation_id = ev.id
               JOIN employee er ON er.id = ev.evaluator_id
               JOIN employee ed ON ed.id = ev.evaluated_id
              WHERE ev.id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new Evaluation(
            (int) $row['id'],
            Employee::fromRow($row, 'evaluator_'),
            Employee::fromRow($row, 'evaluated_'),
            (string) $row['created_at'],
            (string) $row['score'],
            $this->findAnswers($id),
        );
    }

    /**
     * Nota vigente de cada funcionario pedido. Consulta separada de propósito: a travessia de
     * descendentes também serve à autorização, que não deve pagar pelo cálculo de notas.
     *
     * @param list<int> $employeeIds
     * @return array<int,string>
     */
    public function findCurrentScoresFor(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT ce.evaluated_id, es.score
               FROM current_evaluation ce
               JOIN evaluation_score es ON es.evaluation_id = ce.evaluation_id
              WHERE ce.evaluated_id = ANY(string_to_array(:ids, \',\')::int[])',
        );
        $statement->execute(['ids' => implode(',', $employeeIds)]);

        $scores = [];

        foreach ($statement->fetchAll() as $row) {
            $scores[(int) $row['evaluated_id']] = (string) $row['score'];
        }

        return $scores;
    }

    /** A regra da maior hierarquia esta inteira na view; aqui so se pergunta o resultado. */
    public function findCurrentIdFor(int $evaluatedId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT evaluation_id FROM current_evaluation WHERE evaluated_id = :evaluated_id',
        );
        $statement->execute(['evaluated_id' => $evaluatedId]);
        $evaluationId = $statement->fetchColumn();

        return $evaluationId === false ? null : (int) $evaluationId;
    }

    /** @return list<EvaluationSummary> */
    public function findHistoryFor(int $evaluatedId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ev.id,
                    to_char(ev.created_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') AS created_at,
                    es.score,
                    (ce.evaluation_id IS NOT NULL)::int AS is_current,
                    er.id AS evaluator_id,
                    er.name AS evaluator_name,
                    er.email AS evaluator_email,
                    er.position_name AS evaluator_position_name
               FROM evaluation ev
               JOIN evaluation_score es ON es.evaluation_id = ev.id
               JOIN employee er ON er.id = ev.evaluator_id
               LEFT JOIN current_evaluation ce ON ce.evaluation_id = ev.id
              WHERE ev.evaluated_id = :evaluated_id
              ORDER BY ev.created_at DESC, ev.id DESC',
        );
        $statement->execute(['evaluated_id' => $evaluatedId]);

        return array_map(EvaluationSummary::fromRow(...), $statement->fetchAll());
    }

    /** @return list<EvaluationAnswer> */
    private function findAnswers(int $evaluationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ea.question_id, q.name AS question_name, q.weight, ea.answer
               FROM evaluation_answer ea
               JOIN question q ON q.id = ea.question_id
              WHERE ea.evaluation_id = :evaluation_id
              ORDER BY q.id',
        );
        $statement->execute(['evaluation_id' => $evaluationId]);

        return array_map(EvaluationAnswer::fromRow(...), $statement->fetchAll());
    }

    private function translate(Throwable $exception): Throwable
    {
        // O nome do indice distingue esta violacao da chave primaria de evaluation_answer,
        // que tambem e 23505.
        $isWeeklyLimit = $exception instanceof PDOException
            && $exception->getCode() === self::UNIQUE_VIOLATION
            && str_contains($exception->getMessage(), self::WEEKLY_INDEX);

        return $isWeeklyLimit
            ? new WeeklyLimitReachedException(self::WEEKLY_INDEX, 0, $exception)
            : $exception;
    }
}
