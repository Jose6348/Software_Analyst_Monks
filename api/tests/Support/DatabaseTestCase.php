<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap\DatabaseConfig;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = DatabaseConfig::fromEnvironment()->connect();
    }

    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    /**
     * Uma violacao de constraint aborta a transacao inteira no Postgres; o SAVEPOINT
     * devolve a sessao a um estado utilizavel para o resto do teste.
     */
    protected function assertViolates(string $expectedSqlState, callable $operation): void
    {
        self::$pdo->exec('SAVEPOINT expected_violation');

        try {
            $operation();
        } catch (PDOException $exception) {
            self::$pdo->exec('ROLLBACK TO SAVEPOINT expected_violation');
            self::assertSame($expectedSqlState, $exception->getCode());

            return;
        }

        self::fail(sprintf('Esperava violacao de constraint %s, mas a operacao foi aceita.', $expectedSqlState));
    }
}
