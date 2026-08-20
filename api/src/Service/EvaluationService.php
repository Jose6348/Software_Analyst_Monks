<?php

declare(strict_types=1);

namespace App\Service;

use App\Http\ApiException;
use App\Model\Employee;
use App\Model\Evaluation;
use App\Model\EvaluationSummary;
use App\Model\Question;
use App\Model\Subordinate;
use App\Repository\EmployeeRepository;
use App\Repository\EvaluationRepository;
use App\Repository\QuestionRepository;
use App\Repository\WeeklyLimitReachedException;
use LogicException;

final readonly class EvaluationService
{
    private const ANSWER_MIN = 1;
    private const ANSWER_MAX = 4;

    public function __construct(
        private EmployeeRepository $employees,
        private QuestionRepository $questions,
        private EvaluationRepository $evaluations,
        private HierarchyService $hierarchy,
    ) {
    }

    public function create(Employee $evaluator, mixed $payload): Evaluation
    {
        if (!is_array($payload)) {
            throw ApiException::badRequest('O corpo da requisição deve ser um objeto JSON.');
        }

        // Validação antes da autorização: a travessia de hierarquia é a consulta mais cara do
        // sistema e não vale pagá-la por um payload que já nasceu inválido.
        $answers = $this->validateAnswers($payload['answers'] ?? null);
        $evaluated = $this->resolveEvaluated($payload['evaluated_id'] ?? null);

        $this->hierarchy->assertCanAccess($evaluator->id, $evaluated->id);

        return $this->store($evaluator->id, $evaluated->id, $answers);
    }

    /**
     * Subordinados com a nota vigente de cada um — a mesma avaliacao que /latest devolveria,
     * porque ambos leem a view current_evaluation.
     *
     * @return list<Subordinate>
     */
    public function subordinatesWithScores(Employee $leader): array
    {
        $subordinates = $this->hierarchy->subordinatesOf($leader->id);

        $scores = $this->evaluations->findCurrentScoresFor(
            array_map(static fn (Subordinate $s): int => $s->employee->id, $subordinates),
        );

        return array_map(
            static fn (Subordinate $s): Subordinate => $s->withLatestScore($scores[$s->employee->id] ?? null),
            $subordinates,
        );
    }

    /**
     * Avaliacao vigente: a mais recente respeitando a maior hierarquia. Nao e simplesmente a
     * ultima linha gravada — a view current_evaluation resolve o criterio.
     */
    public function latestFor(Employee $viewer, int $evaluatedId): Evaluation
    {
        $this->assertVisible($viewer, $evaluatedId);

        $evaluationId = $this->evaluations->findCurrentIdFor($evaluatedId)
            ?? throw ApiException::notFound('Este funcionário ainda não foi avaliado.');

        return $this->evaluations->findById($evaluationId)
            ?? throw new LogicException('Avaliação vigente não encontrada.');
    }

    /** @return list<EvaluationSummary> */
    public function historyFor(Employee $viewer, int $evaluatedId): array
    {
        $this->assertVisible($viewer, $evaluatedId);

        return $this->evaluations->findHistoryFor($evaluatedId);
    }

    private function assertVisible(Employee $viewer, int $evaluatedId): void
    {
        if ($this->employees->findById($evaluatedId) === null) {
            throw ApiException::notFound('Funcionário não encontrado.');
        }

        $this->hierarchy->assertCanAccess($viewer->id, $evaluatedId);
    }

    /** @param array<int,int> $answers */
    private function store(int $evaluatorId, int $evaluatedId, array $answers): Evaluation
    {
        try {
            $evaluationId = $this->evaluations->create($evaluatorId, $evaluatedId, $answers);
        } catch (WeeklyLimitReachedException) {
            throw ApiException::conflict(
                'Este avaliador já avaliou este funcionário nesta semana.',
                'WEEKLY_LIMIT_REACHED',
            );
        }

        return $this->evaluations->findById($evaluationId)
            ?? throw new LogicException('Avaliação gravada mas não encontrada em seguida.');
    }

    private function resolveEvaluated(mixed $evaluatedId): Employee
    {
        if (!is_int($evaluatedId) || $evaluatedId < 1) {
            throw ApiException::badRequest('O campo evaluated_id deve ser um inteiro positivo.');
        }

        return $this->employees->findById($evaluatedId)
            ?? throw ApiException::notFound('Funcionário avaliado não encontrado.');
    }

    /** @return array<int,int> resposta por id de questão */
    private function validateAnswers(mixed $answers): array
    {
        if (!is_array($answers)) {
            throw ApiException::badRequest('O campo answers deve ser uma lista de respostas.');
        }

        $expected = array_map(
            static fn (Question $question): int => $question->id,
            $this->questions->findAll(),
        );

        // Limita antes do laço: sem isso um payload com milhares de respostas seria percorrido
        // inteiro só para ser recusado no fim.
        if (count($answers) > count($expected)) {
            throw ApiException::badRequest(sprintf(
                'A avaliação exige exatamente %d respostas, mas %d foram enviadas.',
                count($expected),
                count($answers),
            ));
        }

        $byQuestionId = [];

        foreach ($answers as $entry) {
            if (!is_array($entry)) {
                throw ApiException::badRequest('Cada resposta deve ser um objeto com question_id e answer.');
            }

            $questionId = $entry['question_id'] ?? null;
            $answer = $entry['answer'] ?? null;

            if (!is_int($questionId) || !is_int($answer)) {
                throw ApiException::badRequest('Os campos question_id e answer devem ser inteiros.');
            }

            if ($answer < self::ANSWER_MIN || $answer > self::ANSWER_MAX) {
                throw ApiException::badRequest(sprintf(
                    'A resposta da questão %d deve estar entre %d e %d.',
                    $questionId,
                    self::ANSWER_MIN,
                    self::ANSWER_MAX,
                ));
            }

            if (array_key_exists($questionId, $byQuestionId)) {
                throw ApiException::badRequest(sprintf('A questão %d foi respondida mais de uma vez.', $questionId));
            }

            $byQuestionId[$questionId] = $answer;
        }

        $missing = array_diff($expected, array_keys($byQuestionId));
        $unknown = array_diff(array_keys($byQuestionId), $expected);

        if ($missing !== [] || $unknown !== []) {
            throw $this->incompleteQuestionnaire($expected, $missing, $unknown);
        }

        return $byQuestionId;
    }

    /**
     * @param list<int> $expected
     * @param array<int,int> $missing
     * @param array<int,int> $unknown
     */
    private function incompleteQuestionnaire(array $expected, array $missing, array $unknown): ApiException
    {
        return ApiException::badRequest(sprintf(
            'A avaliação exige exatamente as %d questões do questionário. Faltando: [%s]. Desconhecidas: [%s].',
            count($expected),
            implode(', ', $missing),
            implode(', ', $unknown),
        ));
    }
}
