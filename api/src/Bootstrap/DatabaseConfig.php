<?php

declare(strict_types=1);

namespace App\Bootstrap;

use PDO;

final readonly class DatabaseConfig
{
    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $user,
        private string $password,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::env('DB_HOST', 'db'),
            (int) self::env('DB_PORT', '5432'),
            self::env('DB_NAME', 'evaluation'),
            self::env('DB_USER', 'app'),
            self::env('DB_PASSWORD', 'app'),
        );
    }

    public function connect(): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $this->host, $this->port, $this->database);

        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}
