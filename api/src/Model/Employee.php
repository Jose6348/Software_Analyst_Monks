<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final readonly class Employee implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $positionName,
    ) {
    }

    /** O prefixo hidrata avaliador e avaliado a partir de uma mesma linha. @param array<string,mixed> $row */
    public static function fromRow(array $row, string $prefix = ''): self
    {
        return new self(
            (int) $row[$prefix . 'id'],
            (string) $row[$prefix . 'name'],
            (string) $row[$prefix . 'email'],
            (string) $row[$prefix . 'position_name'],
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'position_name' => $this->positionName,
        ];
    }
}
