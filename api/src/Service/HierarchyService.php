<?php

declare(strict_types=1);

namespace App\Service;

use App\Http\ApiException;
use App\Model\Subordinate;
use App\Repository\EmployeeRepository;

/**
 * Ponto único do app para a travessia de descendentes: autorização de escrita e de leitura
 * passam por aqui, nunca refazem a consulta.
 *
 * O desempate por maior hierarquia não vive aqui, e sim nas views `employee_depth` e
 * `current_evaluation` — ele precisa ser aplicado dentro do SQL que escolhe a avaliação, e
 * defini-lo uma vez no banco garante que a lista e o detalhe não possam divergir.
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
            throw ApiException::forbidden('Não é permitido avaliar nem consultar a si mesmo.');
        }

        foreach ($this->subordinatesOf($viewerId) as $subordinate) {
            if ($subordinate->employee->id === $targetId) {
                return;
            }
        }

        throw ApiException::forbidden('Este funcionário não faz parte da sua hierarquia.');
    }
}
