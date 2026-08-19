<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Subordinate;
use App\Repository\EmployeeRepository;

/**
 * Ponto único do app para perguntas sobre a hierarquia. Autorização de escrita, de leitura e
 * o desempate por maior hierarquia devem passar por aqui, nunca refazer a travessia.
 */
final readonly class HierarchyService
{
    public function __construct(private EmployeeRepository $employees)
    {
    }

    /** @return list<Subordinate> */
    public function subordinatesOf(int $leaderId): array
    {
        return $this->employees->findDescendants($leaderId);
    }
}
