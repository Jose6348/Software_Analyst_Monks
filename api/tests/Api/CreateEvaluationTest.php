<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateEvaluationTest extends ApiTestCase
{
    private const ALICE = 1;
    private const BOB = 2;
    private const CAROL = 3;
    private const DAVID = 4;
    private const HENRY = 8;
    private const JAMES = 10;

    public function testLeaderEvaluatesADirectSubordinate(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::JAMES,
            'answers'      => $this->answers([1 => 4, 2 => 3, 3 => 4, 4 => 2, 5 => 1, 6 => 3]),
        ], self::HENRY);

        $body = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(self::HENRY, $body['evaluator']['id']);
        self::assertSame(self::JAMES, $body['evaluated']['id']);
        // 4*25 + 3*20 + 4*20 + 2*15 + 1*10 + 3*10 = 310 -> 310/100
        self::assertSame('3.10', $body['score']);
        self::assertCount(6, $body['answers']);
        self::assertSame('Entrega de Resultados', $body['answers'][0]['question_name']);
        self::assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/',
            $body['created_at'],
        );
    }

    public function testLeaderEvaluatesAnIndirectSubordinate(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::JAMES,
            'answers'      => $this->answers(),
        ], self::ALICE);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testSecondEvaluationOfTheSamePairInTheSameWeekIsRejected(): void
    {
        $payload = ['evaluated_id' => self::JAMES, 'answers' => $this->answers()];

        self::assertSame(201, $this->postJson('/api/evaluations', $payload, self::HENRY)->getStatusCode());

        $response = $this->postJson('/api/evaluations', $payload, self::HENRY);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('WEEKLY_LIMIT_REACHED', $this->decode($response)['error']['code']);
        self::assertSame(1, $this->countEvaluations());
    }

    public function testAnotherLeaderStillEvaluatesTheSameEmployeeInTheSameWeek(): void
    {
        $payload = ['evaluated_id' => self::JAMES, 'answers' => $this->answers()];

        self::assertSame(201, $this->postJson('/api/evaluations', $payload, self::HENRY)->getStatusCode());

        // A trava e por par: o chefe do Henry avalia James mesmo com a avaliacao do Henry no periodo.
        self::assertSame(201, $this->postJson('/api/evaluations', $payload, self::DAVID)->getStatusCode());
        self::assertSame(2, $this->countEvaluations());
    }

    public function testEvaluatingSomeoneOutsideTheHierarchyIsForbidden(): void
    {
        // Carol chefia o time de financas; James esta na arvore do Bob.
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::JAMES,
            'answers'      => $this->answers(),
        ], self::CAROL);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('FORBIDDEN', $this->decode($response)['error']['code']);
        self::assertSame(0, $this->countEvaluations());
    }

    public function testEvaluatingOneselfIsForbidden(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::HENRY,
            'answers'      => $this->answers(),
        ], self::HENRY);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testEvaluatingASuperiorIsForbidden(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::BOB,
            'answers'      => $this->answers(),
        ], self::HENRY);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUnknownEvaluatedEmployeeIsNotFound(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => 999,
            'answers'      => $this->answers(),
        ], self::HENRY);

        self::assertSame(404, $response->getStatusCode());
    }

    /** @return array<string,array{mixed}> */
    public static function invalidPayloadProvider(): array
    {
        $complete = [];

        foreach (range(1, 6) as $questionId) {
            $complete[] = ['question_id' => $questionId, 'answer' => 3];
        }

        $firstFive = array_slice($complete, 0, 5);

        return [
            'sem evaluated_id'        => [['answers' => $complete]],
            'evaluated_id como texto' => [['evaluated_id' => '10', 'answers' => $complete]],
            'evaluated_id zero'       => [['evaluated_id' => 0, 'answers' => $complete]],
            'sem answers'             => [['evaluated_id' => 10]],
            'answers vazio'           => [['evaluated_id' => 10, 'answers' => []]],
            'cinco respostas'         => [['evaluated_id' => 10, 'answers' => $firstFive]],
            'sete respostas'          => [['evaluated_id' => 10, 'answers' => [...$complete, ['question_id' => 7, 'answer' => 2]]]],
            'questao duplicada'       => [['evaluated_id' => 10, 'answers' => [...$firstFive, ['question_id' => 1, 'answer' => 2]]]],
            'resposta zero'           => [['evaluated_id' => 10, 'answers' => [...$firstFive, ['question_id' => 6, 'answer' => 0]]]],
            'resposta cinco'          => [['evaluated_id' => 10, 'answers' => [...$firstFive, ['question_id' => 6, 'answer' => 5]]]],
            'resposta como texto'     => [['evaluated_id' => 10, 'answers' => [...$firstFive, ['question_id' => 6, 'answer' => '3']]]],
            'questao inexistente'     => [['evaluated_id' => 10, 'answers' => [...$firstFive, ['question_id' => 99, 'answer' => 3]]]],
        ];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsRejectedWithoutWritingAnything(mixed $payload): void
    {
        $response = $this->postJson('/api/evaluations', $payload, self::HENRY);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('VALIDATION_ERROR', $this->decode($response)['error']['code']);
        self::assertSame(0, $this->countEvaluations());
    }

    public function testEvaluationRequiresAnIdentity(): void
    {
        $response = $this->postJson('/api/evaluations', [
            'evaluated_id' => self::JAMES,
            'answers'      => $this->answers(),
        ]);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('MISSING_EMPLOYEE_ID', $this->decode($response)['error']['code']);
    }

    public function testNoRouteCanMutateAnEvaluation(): void
    {
        // Assercao sobre a tabela de rotas, e nao sobre uma URL escolhida a dedo: uma rota de
        // mutacao adicionada em qualquer caminho de /evaluations cai aqui.
        $mutating = [];

        foreach ($this->app()->getRouteCollector()->getRoutes() as $route) {
            if (!str_starts_with($route->getPattern(), '/api/evaluations')) {
                continue;
            }

            foreach ($route->getMethods() as $method) {
                if (!in_array($method, ['GET', 'POST', 'HEAD', 'OPTIONS'], true)) {
                    $mutating[] = $method . ' ' . $route->getPattern();
                }
            }
        }

        self::assertSame([], $mutating, 'Respostas enviadas nunca podem ser alteradas.');
    }

    public function testMalformedJsonBodyIsRejectedAsAValidationError(): void
    {
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest('POST', '/api/evaluations')
            ->withHeader('X-Employee-Id', (string) self::HENRY)
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write('"apenas uma string"');
        $request->getBody()->rewind();

        $response = $this->app()->handle($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('VALIDATION_ERROR', $this->decode($response)['error']['code']);
    }

    /**
     * @param array<int,int> $answersByQuestionId
     * @return list<array{question_id:int,answer:int}>
     */
    private function answers(array $answersByQuestionId = []): array
    {
        $answersByQuestionId = $answersByQuestionId === []
            ? array_fill_keys(range(1, 6), 3)
            : $answersByQuestionId;

        $answers = [];

        foreach ($answersByQuestionId as $questionId => $answer) {
            $answers[] = ['question_id' => $questionId, 'answer' => $answer];
        }

        return $answers;
    }

    private function countEvaluations(): int
    {
        $statement = self::$pdo->prepare('SELECT count(*) FROM evaluation');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
