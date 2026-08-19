<?php

declare(strict_types=1);

use App\Bootstrap\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

AppFactory::create()->run();
