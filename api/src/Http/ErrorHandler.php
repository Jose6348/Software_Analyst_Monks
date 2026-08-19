<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

final readonly class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $errorCode, $message] = match (true) {
            $exception instanceof ApiException => [
                $exception->statusCode,
                $exception->errorCode,
                $exception->getMessage(),
            ],
            $exception instanceof HttpNotFoundException => [404, 'NOT_FOUND', 'Rota não encontrada.'],
            $exception instanceof HttpMethodNotAllowedException => [405, 'METHOD_NOT_ALLOWED', 'Método não permitido.'],
            default => [500, 'INTERNAL_ERROR', 'Erro interno do servidor.'],
        };

        if ($status === 500 && $logErrors) {
            error_log((string) $exception);
        }

        $response = $this->responseFactory->createResponse();

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        return JsonResponse::write(
            $response,
            ['error' => ['code' => $errorCode, 'message' => $message]],
            $status,
        );
    }
}
