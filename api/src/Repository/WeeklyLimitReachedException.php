<?php

declare(strict_types=1);

namespace App\Repository;

use RuntimeException;

/**
 * O indice unico recusou a gravacao. Quem traduz isso em regra de negocio e resposta HTTP e o
 * EvaluationService; aqui so se registra o que o banco disse.
 */
final class WeeklyLimitReachedException extends RuntimeException
{
}
