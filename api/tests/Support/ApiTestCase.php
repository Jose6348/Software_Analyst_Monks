<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap\AppFactory;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercita a pilha HTTP inteira — rota, middleware, controller, service, repository —
 * contra a mesma conexao (e portanto a mesma transacao) do teste.
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    private ?App $app = null;

    protected function request(string $method, string $path, ?int $employeeId = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        if ($employeeId !== null) {
            $request = $request->withHeader('X-Employee-Id', (string) $employeeId);
        }

        return $this->app()->handle($request);
    }

    protected function requestWithRawHeader(string $method, string $path, string $employeeId): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path)
            ->withHeader('X-Employee-Id', $employeeId);

        return $this->app()->handle($request);
    }

    /** @return array<mixed> */
    protected function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function app(): App
    {
        return $this->app ??= AppFactory::create([PDO::class => fn (): PDO => self::$pdo]);
    }
}
