<?php

declare(strict_types=1);

namespace App\Service;

use App\Http\ApiException;
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

    /** Autorizacao de escrita e de leitura; reusa a travessia acima para nao duplicar a CTE. */
    public function assertCanAccess(int $viewerId, int $targetId): void
    {
        // Explicito, e nao apoiado em "ninguem e descendente de si mesmo": um ciclo em
        // leader_lead faria a travessia devolver o proprio viewer.
        if ($viewerId === $targetId) {
            throw ApiException::forbidden('Não é permitido avaliar a si mesmo.');
        }

        foreach ($this->subordinatesOf($viewerId) as $subordinate) {
            if ($subordinate->employee->id === $targetId) {
                return;
            }
        }

        throw ApiException::forbidden('Este funcionário não faz parte da sua hierarquia.');
    }
}
