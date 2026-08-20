<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/** Entrada de histórico: sem as respostas, que só a avaliação vigente exibe. */
final readonly class EvaluationSummary implements JsonSerializable
{
    public function __construct(
        public int $id,
        public Employee $evaluator,
        public string $createdAt,
        public string $score,
        public bool $isCurrent,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            Employee::fromRow($row, 'evaluator_'),
            (string) $row['created_at'],
            (string) $row['score'],
            (bool) (int) $row['is_current'],
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'evaluator'  => $this->evaluator,
            'created_at' => $this->createdAt,
            'score'      => $this->score,
            'is_current' => $this->isCurrent,
        ];
    }
}
