<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDOException;

/** Isolamento por transação; não serve aos testes de HTTP porque o PDO não aninha transações. */
abstract class TransactionalTestCase extends DatabaseTestCase
{
    protected function setUp(): void
    {
        // Antes da transacao: os testes de HTTP commitam avaliacoes, e sem isso o estado
        // inicial aqui dependeria da ordem de execucao das classes.
        self::$pdo->exec('TRUNCATE evaluation_answer, evaluation RESTART IDENTITY');
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::$pdo->rollBack();
    }

    /** O SAVEPOINT e necessario porque uma violacao aborta a transacao inteira no Postgres. */
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
