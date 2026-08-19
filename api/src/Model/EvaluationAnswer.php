<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class EvaluationAnswer implements JsonSerializable
{
    public function __construct(
        public int $questionId,
        public string $questionName,
        public int $weight,
        public int $answer,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['question_id'],
            (string) $row['question_name'],
            (int) $row['weight'],
            (int) $row['answer'],
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'question_id'   => $this->questionId,
            'question_name' => $this->questionName,
            'weight'        => $this->weight,
            'answer'        => $this->answer,
        ];
    }
}
