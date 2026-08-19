<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\ErrorHandler;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(): App
    {
        $container = (new ContainerBuilder())
            ->useAutowiring(true)
            ->addDefinitions(__DIR__ . '/definitions.php')
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
