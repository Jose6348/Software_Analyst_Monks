<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Http\ApiException;
use App\Model\Subordinate;
use App\Repository\EmployeeRepository;
use App\Service\HierarchyService;
use App\Tests\Support\TransactionalTestCase;

final class HierarchyServiceTest extends TransactionalTestCase
{
    private const ALICE = 1;
    private const BOB = 2;
    private const DAVID = 4;
    private const HENRY = 8;
    private const JAMES = 10;
    private const KAREN = 11;

    private HierarchyService $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = new HierarchyService(new EmployeeRepository(self::$pdo));
    }

    public function testDepthGrowsWithEachLevelBelowTheLeader(): void
    {
        $depths = $this->depthsFrom(self::ALICE);

        self::assertSame(1, $depths[self::BOB]);
        self::assertSame(2, $depths[self::DAVID]);
        self::assertSame(3, $depths[self::HENRY]);
        self::assertSame(4, $depths[self::JAMES]);
    }

    public function testEachLeaderReachesItsWholeSubtree(): void
    {
        self::assertCount(19, $this->hierarchy->subordinatesOf(self::ALICE));
        self::assertCount(12, $this->hierarchy->subordinatesOf(self::BOB));
        self::assertCount(4, $this->hierarchy->subordinatesOf(self::DAVID));
        self::assertCount(2, $this->hierarchy->subordinatesOf(self::HENRY));
        self::assertSame([], $this->hierarchy->subordinatesOf(self::JAMES));
    }

    public function testEachSubordinateCarriesItsImmediateLeader(): void
    {
        $leaders = $this->leadersFrom(self::DAVID);

        // Diretos apontam para o proprio David; indiretos, para quem de fato os lidera.
        self::assertSame(self::DAVID, $leaders[self::HENRY]);
        self::assertSame(self::HENRY, $leaders[self::JAMES]);
        self::assertSame(self::HENRY, $leaders[self::KAREN]);
    }

    public function testTheImmediateLeaderFollowsTheShortestPath(): void
    {
        // Alice alcanca James por Henry (nivel 4) e, com este atalho, direto (nivel 1).
        $this->addLeadership(self::ALICE, self::JAMES);

        self::assertSame(self::ALICE, $this->leadersFrom(self::ALICE)[self::JAMES]);
        self::assertSame(1, $this->depthsFrom(self::ALICE)[self::JAMES]);
    }

    public function testNobodyIsTheirOwnSubordinate(): void
    {
        foreach ([self::ALICE, self::BOB, self::DAVID, self::HENRY] as $leaderId) {
            self::assertArrayNotHasKey(
                $leaderId,
                $this->depthsFrom($leaderId),
                sprintf('Funcionário %d apareceu como subordinado de si mesmo.', $leaderId),
            );
        }
    }

    public function testACycleInTheGraphDoesNotHangTheTraversal(): void
    {
        // Henry -> James -> Henry. Sem o limite de profundidade a recursao nao terminaria:
        // o UNION deduplica pares (id, depth) e a profundidade cresce a cada volta.
        $this->addLeadership(self::JAMES, self::HENRY);

        $depths = $this->depthsFrom(self::HENRY);

        self::assertCount(3, $depths);
        self::assertSame(1, $depths[self::JAMES]);
        self::assertSame(2, $depths[self::HENRY], 'Henry volta como descendente de si mesmo pelo ciclo.');
    }

    public function testSelfAccessIsBlockedEvenWhenACycleMakesSomeoneTheirOwnDescendant(): void
    {
        $this->addLeadership(self::JAMES, self::HENRY);

        // O ciclo faz a travessia devolver o proprio Henry; a autorizacao nao pode se apoiar nisso.
        self::assertArrayHasKey(self::HENRY, $this->depthsFrom(self::HENRY));

        $this->expectException(ApiException::class);
        $this->hierarchy->assertCanAccess(self::HENRY, self::HENRY);
    }

    /** @return array<int,int> */
    private function leadersFrom(int $leaderId): array
    {
        $leaders = [];

        foreach ($this->hierarchy->subordinatesOf($leaderId) as $subordinate) {
            $leaders[$subordinate->employee->id] = $subordinate->leaderId;
        }

        return $leaders;
    }

    /** @return array<int,int> */
    private function depthsFrom(int $leaderId): array
    {
        $depths = [];

        foreach ($this->hierarchy->subordinatesOf($leaderId) as $subordinate) {
            self::assertInstanceOf(Subordinate::class, $subordinate);
            $depths[$subordinate->employee->id] = $subordinate->depth;
        }

        return $depths;
    }

    private function addLeadership(int $leaderId, int $leadId): void
    {
        $statement = self::$pdo->prepare(
            'INSERT INTO leader_lead (leader_id, lead_id) VALUES (:leader_id, :lead_id)',
        );
        $statement->execute(['leader_id' => $leaderId, 'lead_id' => $leadId]);
    }
}
