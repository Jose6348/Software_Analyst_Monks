<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Middleware\CurrentEmployeeMiddleware;
use App\Repository\EmployeeRepository;
use App\Service\HierarchyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class EmployeeController
{
    public function __construct(
        private EmployeeRepository $employees,
        private HierarchyService $hierarchy,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::write($response, $this->employees->findAll());
    }

    public function subordinates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $leader = CurrentEmployeeMiddleware::from($request);

        return JsonResponse::write($response, $this->hierarchy->subordinatesOf($leader->id));
    }
}
