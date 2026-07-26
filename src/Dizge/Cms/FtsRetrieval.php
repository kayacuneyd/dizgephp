<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** SQLite FTS5 implementation of the public retrieval contract. */
final class FtsRetrieval implements Retrieval
{
    public function __construct(private ContentRepository $repository) {}

    public function search(string $query, int $limit = 5): array
    {
        return $this->repository->searchPublished($query, $limit);
    }
}
