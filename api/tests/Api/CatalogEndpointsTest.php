<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class CatalogEndpointsTest extends ApiTestCase
{
    public function testEmployeesListsEveryoneWithoutRequiringAnIdentity(): void
    {
        $response = $this->request('GET', '/api/employees');
        $employees = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(20, $employees);
        self::assertSame(
            ['id', 'name', 'email', 'position_name'],
            array_keys($employees[0]),
        );
        self::assertSame('Alice Hartman', $employees[0]['name']);
    }

    public function testQuestionsReturnTheCaseWeights(): void
    {
        $response = $this->request('GET', '/api/questions');
        $questions = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(6, $questions);
        self::assertSame('Entrega de Resultados', $questions[0]['name']);
        self::assertSame(25, $questions[0]['weight']);
        self::assertSame(100, array_sum(array_column($questions, 'weight')));
    }

    public function testAccentedNamesSurviveTheJsonRoundTrip(): void
    {
        $questions = $this->decode($this->request('GET', '/api/questions'));

        self::assertSame('Execução e Qualidade do Trabalho', $questions[1]['name']);
    }

    public function testUnknownRouteKeepsTheStandardErrorShape(): void
    {
        $response = $this->request('GET', '/api/nao-existe');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('NOT_FOUND', $this->decode($response)['error']['code']);
    }
}
