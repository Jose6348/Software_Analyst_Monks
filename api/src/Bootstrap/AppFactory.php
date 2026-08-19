<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\ErrorHandler;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    /**
     * @param array<string,mixed> $overrides definicoes que sobrescrevem as padrao; usado pelos
     *                                        testes para injetar o PDO da transacao de teste.
     */
    public static function create(array $overrides = []): App
    {
        $container = (new ContainerBuilder())
            ->useAutowiring(true)
            ->addDefinitions(__DIR__ . '/definitions.php')
            ->addDefinitions($overrides)
            ->build();

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        (require __DIR__ . '/routes.php')($app);

        $app->addErrorMiddleware(false, true, true)
            ->setDefaultErrorHandler($container->get(ErrorHandler::class));

        return $app;
    }
}
