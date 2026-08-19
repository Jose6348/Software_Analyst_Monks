<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class Evaluation implements JsonSerializable
{
    /** @param list<EvaluationAnswer> $answers */
    public function __construct(
        public int $id,
        public Employee $evaluator,
        public Employee $evaluated,
        public string $createdAt,
        public string $score,
        public array $answers,
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'evaluator'  => $this->evaluator,
            'evaluated'  => $this->evaluated,
            'created_at' => $this->createdAt,
            'score'      => $this->score,
            'answers'    => $this->answers,
        ];
    }
}
