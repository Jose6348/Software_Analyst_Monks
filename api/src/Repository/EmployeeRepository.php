<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Employee;
use App\Model\Subordinate;
use PDO;

final readonly class EmployeeRepository
{
    private const MAX_DEPTH = 20;

    /** `employee.id` e int4: um id acima disso nao existe e faria o Postgres estourar 22003. */
    private const MAX_ID = 2147483647;

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<Employee> */
    public function findAll(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, position_name FROM employee ORDER BY name',
        );
        $statement->execute();

        return array_map(Employee::fromRow(...), $statement->fetchAll());
    }

    public function findById(int $id): ?Employee
    {
        if ($id < 1 || $id > self::MAX_ID) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, name, email, position_name FROM employee WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Employee::fromRow($row);
    }

    /**
     * Descendentes diretos e indiretos, com a menor profundidade até cada um.
     *
     * `leader_lead` é um grafo N:N: o mesmo funcionário pode ser alcançado por mais de um
     * caminho (daí o MIN) e nada no schema impede um ciclo. O UNION deduplica pares
     * (employee_id, depth), mas num ciclo a profundidade cresce a cada volta e o par nunca
     * se repete — quem corta a recursão é o limite de profundidade.
     *
     * Não traz notas: esta consulta também serve à autorização, que só precisa do conjunto.
     *
     * O DISTINCT ON escolhe, para cada funcionário, o caminho de menor profundidade e devolve o
     * líder daquele caminho — é o que permite ao front remontar a árvore. Havendo dois caminhos
     * igualmente curtos, vence o líder de menor id, para o resultado não variar entre execuções.
     *
     * @return list<Subordinate>
     */
    public function findDescendants(int $leaderId): array
    {
        $statement = $this->pdo->prepare(
            'WITH RECURSIVE subordinates AS (
                 SELECT lead_id AS employee_id, leader_id, 1 AS depth
                   FROM leader_lead
                  WHERE leader_id = :leader_id
                 UNION
                 SELECT ll.lead_id, s.employee_id, s.depth + 1
                   FROM leader_lead ll
                   JOIN subordinates s ON ll.leader_id = s.employee_id
                  WHERE s.depth < :max_depth
             ),
             shallowest AS (
                 SELECT DISTINCT ON (employee_id) employee_id, leader_id, depth
                   FROM subordinates
                  ORDER BY employee_id, depth, leader_id
             )
             SELECT e.id, e.name, e.email, e.position_name, s.depth, s.leader_id
               FROM shallowest s
               JOIN employee e ON e.id = s.employee_id
              ORDER BY s.depth, e.name',
        );
        $statement->execute([
            'leader_id' => $leaderId,
            'max_depth' => self::MAX_DEPTH,
        ]);

        return array_map(Subordinate::fromRow(...), $statement->fetchAll());
    }
}
