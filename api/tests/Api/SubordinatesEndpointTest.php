<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SubordinatesEndpointTest extends ApiTestCase
{
    private const ALICE = 1;
    private const DAVID = 4;
    private const HENRY = 8;
    private const JAMES = 10;

    public function testMissingHeaderIsRejected(): void
    {
        $response = $this->request('GET', '/api/me/subordinates');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('MISSING_EMPLOYEE_ID', $this->decode($response)['error']['code']);
    }

    /** @return list<array{string}> */
    public static function invalidHeaderProvider(): array
    {
        return [['abc'], ['0'], ['-1'], ['1.5'], ['1; DROP TABLE employee']];
    }

    #[DataProvider('invalidHeaderProvider')]
    public function testNonPositiveIntegerHeaderIsRejected(string $header): void
    {
        $response = $this->requestWithRawHeader('GET', '/api/me/subordinates', $header);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('INVALID_EMPLOYEE_ID', $this->decode($response)['error']['code']);
    }

    public function testUnknownEmployeeIsNotFound(): void
    {
        $response = $this->request('GET', '/api/me/subordinates', 999);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('NOT_FOUND', $this->decode($response)['error']['code']);
    }

    public function testIdBeyondTheIntegerColumnRangeIsNotFound(): void
    {
        // employee.id e int4: sem barreira o Postgres levanta 22003 e a resposta viraria 500.
        $response = $this->requestWithRawHeader('GET', '/api/me/subordinates', '2147483648');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCeoReachesEveryoneElse(): void
    {
        $subordinates = $this->decode($this->request('GET', '/api/me/subordinates', self::ALICE));

        self::assertCount(19, $subordinates);
    }

    public function testDirectLeaderSeesOnlyItsOwnTeam(): void
    {
        $subordinates = $this->decode($this->request('GET', '/api/me/subordinates', self::HENRY));

        self::assertSame(
            ['James Watanabe', 'Karen Oliveira'],
            array_column($subordinates, 'name'),
        );
        self::assertSame([true, true], array_column($subordinates, 'is_direct'));
    }

    public function testLeafEmployeeHasNoSubordinates(): void
    {
        $response = $this->request('GET', '/api/me/subordinates', self::JAMES);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->decode($response));
    }

    public function testIndirectSubordinatesAreIncludedAndFlagged(): void
    {
        $subordinates = $this->decode($this->request('GET', '/api/me/subordinates', self::DAVID));

        $byName = array_column($subordinates, null, 'name');

        self::assertCount(4, $subordinates);
        self::assertSame(1, $byName['Henry Patel']['depth']);
        // O leader_id sustenta a arvore do dashboard: diretos apontam para o lider atual.
        self::assertSame(self::DAVID, $byName['Henry Patel']['leader_id']);
        self::assertSame(self::HENRY, $byName['James Watanabe']['leader_id']);
        self::assertTrue($byName['Henry Patel']['is_direct']);
        self::assertSame(2, $byName['James Watanabe']['depth']);
        self::assertFalse($byName['James Watanabe']['is_direct']);
    }
}
