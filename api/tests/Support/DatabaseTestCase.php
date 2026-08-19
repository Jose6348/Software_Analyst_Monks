<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap\DatabaseConfig;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = DatabaseConfig::fromEnvironment()->connect();
    }
}
