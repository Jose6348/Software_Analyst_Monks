<?php

declare(strict_types=1);

use App\Http\JsonResponse;
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
    });
};
