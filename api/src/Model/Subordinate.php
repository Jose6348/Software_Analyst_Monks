<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class Subordinate implements JsonSerializable
{
    public function __construct(
        public Employee $employee,
        public int $depth,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(Employee::fromRow($row), (int) $row['depth']);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            ...$this->employee->jsonSerialize(),
            'depth'     => $this->depth,
            'is_direct' => $this->depth === 1,
        ];
    }
}
