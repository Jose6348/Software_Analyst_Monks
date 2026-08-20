<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Middleware\CurrentEmployeeMiddleware;
use App\Service\EvaluationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class EvaluationController
{
    public function __construct(private EvaluationService $evaluations)
    {
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $evaluator = CurrentEmployeeMiddleware::from($request);
        $evaluation = $this->evaluations->create($evaluator, $request->getParsedBody());

        return JsonResponse::write($response, $evaluation, 201);
    }

    /** @param array<string,string> $args */
    public function latest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $viewer = CurrentEmployeeMiddleware::from($request);

        return JsonResponse::write($response, $this->evaluations->latestFor($viewer, (int) $args['id']));
    }

    /** @param array<string,string> $args */
    public function history(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $viewer = CurrentEmployeeMiddleware::from($request);

        return JsonResponse::write($response, $this->evaluations->historyFor($viewer, (int) $args['id']));
    }
}
