<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class Subordinate implements JsonSerializable
{
    public function __construct(
        public Employee $employee,
        public int $depth,
        public int $leaderId,
        public ?string $latestScore,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(Employee::fromRow($row), (int) $row['depth'], (int) $row['leader_id'], null);
    }

    public function withLatestScore(?string $latestScore): self
    {
        return new self($this->employee, $this->depth, $this->leaderId, $latestScore);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            ...$this->employee->jsonSerialize(),
            'depth'        => $this->depth,
            'leader_id'    => $this->leaderId,
            'is_direct'    => $this->depth === 1,
            'latest_score' => $this->latestScore,
        ];
    }
}
