<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class Question implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public int $weight,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (int) $row['weight'],
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'weight' => $this->weight,
        ];
    }
}
