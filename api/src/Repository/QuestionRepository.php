<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Question;
use PDO;

final readonly class QuestionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<Question> */
    public function findAll(): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, weight FROM question ORDER BY id');
        $statement->execute();

        return array_map(Question::fromRow(...), $statement->fetchAll());
    }
}
