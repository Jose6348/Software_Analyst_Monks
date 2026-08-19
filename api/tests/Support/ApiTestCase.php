<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap\AppFactory;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercita a pilha HTTP inteira contra o banco de testes. Sem transação: o repositório abre a
 * sua própria ao gravar, e o PDO não aninha transações.
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    private static ?App $app = null;

    protected function setUp(): void
    {
        self::$pdo->exec('TRUNCATE evaluation_answer, evaluation RESTART IDENTITY');
    }

    protected function request(string $method, string $path, ?int $employeeId = null): ResponseInterface
    {
        return $this->app()->handle($this->buildRequest($method, $path, $employeeId));
    }

    protected function requestWithRawHeader(string $method, string $path, string $employeeId): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path)
            ->withHeader('X-Employee-Id', $employeeId);

        return $this->app()->handle($request);
    }

    /** @param array<string,mixed> $payload */
    protected function postJson(string $path, array $payload, ?int $employeeId = null): ResponseInterface
    {
        $request = $this->buildRequest('POST', $path, $employeeId)
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        return $this->app()->handle($request);
    }

    /** @return array<mixed> */
    protected function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildRequest(string $method, string $path, ?int $employeeId): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        return $employeeId === null ? $request : $request->withHeader('X-Employee-Id', (string) $employeeId);
    }

    protected function app(): App
    {
        // A closure resolve o PDO estatico de forma tardia, entao um app so serve a suite toda.
        return self::$app ??= AppFactory::create([PDO::class => fn (): PDO => self::$pdo]);
    }
}
