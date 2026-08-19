<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\Subordinate;
use App\Repository\EmployeeRepository;
use App\Service\HierarchyService;
use App\Tests\Support\DatabaseTestCase;

final class HierarchyServiceTest extends DatabaseTestCase
{
    private const ALICE = 1;
    private const BOB = 2;
    private const DAVID = 4;
    private const HENRY = 8;
    private const JAMES = 10;

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
