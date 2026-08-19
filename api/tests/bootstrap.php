<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// A suite nunca fala com o banco da aplicacao: ela trunca tabelas entre os casos.
putenv('DB_NAME=' . (getenv('DB_NAME') ?: 'evaluation') . '_test');
