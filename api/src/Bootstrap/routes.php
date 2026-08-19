<?php

declare(strict_types=1);

use App\Controller\EmployeeController;
use App\Controller\EvaluationController;
use App\Controller\QuestionController;
use App\Http\JsonResponse;
use App\Middleware\CurrentEmployeeMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

// As closures nao podem ser `static`: o Slim vincula cada callable ao container antes de resolve-la.
return static function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get(
            '/health',
            fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
                => JsonResponse::write($response, ['status' => 'ok']),
        );

        // Catalogo publico: alimenta o seletor de lider e o formulario, antes de haver lider escolhido.
        $api->get('/employees', [EmployeeController::class, 'index']);
        $api->get('/questions', [QuestionController::class, 'index']);

        $api->get('/me/subordinates', [EmployeeController::class, 'subordinates'])
            ->add(CurrentEmployeeMiddleware::class);

        // Sem PUT/PATCH/DELETE: a imutabilidade das respostas e garantida pela ausencia de rota.
        $api->post('/evaluations', [EvaluationController::class, 'store'])
            ->add(CurrentEmployeeMiddleware::class);
    });
};
