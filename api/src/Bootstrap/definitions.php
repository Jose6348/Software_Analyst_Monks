<?php

declare(strict_types=1);

use App\Bootstrap\DatabaseConfig;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;

return [
    PDO::class                       => static fn (): PDO => DatabaseConfig::fromEnvironment()->connect(),
    ResponseFactoryInterface::class  => static fn (): ResponseFactoryInterface => new ResponseFactory(),
];
