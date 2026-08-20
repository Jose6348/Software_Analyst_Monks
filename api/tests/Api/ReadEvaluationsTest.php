<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReadEvaluationsTest extends ApiTestCase
{
    private const ALICE = 1;
    private const BOB = 2;
    private const CAROL = 3;
    private const DAVID = 4;
    private const HENRY = 8;
    private const JAMES = 10;
    private const KAREN = 11;

    public function testDirectLeaderReadsTheEvaluationItWrote(): void
    {
        $this->evaluate(self::HENRY, self::JAMES);

        $response = $this->request('GET', '/api/employees/10/evaluations/latest', self::HENRY);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::JAMES, $body['evaluated']['id']);
        self::assertSame(self::HENRY, $body['evaluator']['id']);
        self::assertCount(6, $body['answers']);
        self::assertSame('3.00', $body['score']);
    }

    public function testLatestAppliesTheHighestHierarchyRule(): void
    {
        $this->evaluate(self::HENRY, self::JAMES);
        $this->evaluate(self::BOB, self::JAMES);

        // David enxerga James, e o que ele ve e a avaliacao do Bob — o avaliador mais alto.
        $body = $this->decode($this->request('GET', '/api/employees/10/evaluations/latest', self::DAVID));

        self::assertSame(self::BOB, $body['evaluator']['id']);
    }

    public function testHistoryListsEveryEvaluationAndFlagsTheCurrentOne(): void
    {
        $this->evaluate(self::HENRY, self::JAMES);
        $this->evaluate(self::BOB, self::JAMES);

        $history = $this->decode($this->request('GET', '/api/employees/10/evaluations', self::DAVID));

        $currentEvaluators = [];

        foreach ($history as $entry) {
            if ($entry['is_current']) {
                $currentEvaluators[] = $entry['evaluator']['id'];
            }
        }

        self::assertCount(2, $history);
        self::assertSame([self::BOB], $currentEvaluators, 'exatamente uma entrada e a vigente');
    }

    public function testSubordinatesListAgreesWithTheDetailScreen(): void
    {
        $this->evaluate(self::HENRY, self::JAMES, [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4]);
        $this->evaluate(self::BOB, self::JAMES, [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1]);

        $subordinates = $this->decode($this->request('GET', '/api/me/subordinates', self::DAVID));
        $james = array_values(array_filter($subordinates, static fn (array $s): bool => $s['id'] === self::JAMES))[0];

        $latest = $this->decode($this->request('GET', '/api/employees/10/evaluations/latest', self::DAVID));

        // A nota da lista e a nota do detalhe tem que ser a mesma avaliacao — a do Bob, nao a do Henry.
        self::assertSame('1.00', $james['latest_score']);
        self::assertSame($latest['score'], $james['latest_score']);
    }

    public function testNeverEvaluatedSubordinateHasNullScore(): void
    {
        $subordinates = $this->decode($this->request('GET', '/api/me/subordinates', self::HENRY));

        self::assertNull($subordinates[0]['latest_score']);
        self::assertNull($subordinates[1]['latest_score']);
    }

    public function testLatestIsNotFoundWhenTheEmployeeWasNeverEvaluated(): void
    {
        $response = $this->request('GET', '/api/employees/10/evaluations/latest', self::HENRY);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testHistoryOfANeverEvaluatedSubordinateIsEmpty(): void
    {
        $response = $this->request('GET', '/api/employees/10/evaluations', self::HENRY);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->decode($response));
    }

    /** @return array<string,array{int,int}> */
    public static function forbiddenViewProvider(): array
    {
        return [
            'a propria avaliacao' => [self::JAMES, self::JAMES],
            'a de um par'         => [self::JAMES, self::KAREN],
            'a de um superior'    => [self::JAMES, self::HENRY],
            'a de outra arvore'   => [self::CAROL, self::JAMES],
            'a do proprio chefe'  => [self::HENRY, self::DAVID],
        ];
    }

    #[DataProvider('forbiddenViewProvider')]
    public function testOnlySubordinatesAreVisible(int $viewerId, int $targetId): void
    {
        $this->evaluate(self::ALICE, $targetId);

        foreach (['', '/latest'] as $suffix) {
            $response = $this->request('GET', "/api/employees/{$targetId}/evaluations{$suffix}", $viewerId);

            self::assertSame(403, $response->getStatusCode(), 'rota ' . $suffix);
            self::assertSame('FORBIDDEN', $this->decode($response)['error']['code']);
        }
    }

    public function testIndirectSubordinateIsVisible(): void
    {
        $this->evaluate(self::HENRY, self::JAMES);

        // Alice esta a 4 niveis de James e mesmo assim enxerga.
        self::assertSame(200, $this->request('GET', '/api/employees/10/evaluations', self::ALICE)->getStatusCode());
    }

    public function testUnknownEmployeeIsNotFound(): void
    {
        $response = $this->request('GET', '/api/employees/999/evaluations', self::ALICE);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testIdBeyondTheIntegerColumnRangeIsNotFound(): void
    {
        foreach (['', '/latest'] as $suffix) {
            $response = $this->request('GET', "/api/employees/2147483648/evaluations{$suffix}", self::ALICE);

            self::assertSame(404, $response->getStatusCode(), 'rota ' . $suffix);
        }
    }

    public function testReadingRequiresAnIdentity(): void
    {
        $response = $this->request('GET', '/api/employees/10/evaluations');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('MISSING_EMPLOYEE_ID', $this->decode($response)['error']['code']);
    }

    /** @param array<int,int> $answers */
    private function evaluate(int $evaluatorId, int $evaluatedId, array $answers = []): void
    {
        $answers = $answers === [] ? array_fill_keys(range(1, 6), 3) : $answers;
        $payload = [];

        foreach ($answers as $questionId => $answer) {
            $payload[] = ['question_id' => $questionId, 'answer' => $answer];
        }

        $response = $this->postJson(
            '/api/evaluations',
            ['evaluated_id' => $evaluatedId, 'answers' => $payload],
            $evaluatorId,
        );

        self::assertSame(201, $response->getStatusCode(), 'fixture de avaliacao falhou');
    }
}
