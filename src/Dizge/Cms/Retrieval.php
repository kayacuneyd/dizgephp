<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Public-only retrieval contract; a vector adapter can replace this FTS implementation later. */
interface Retrieval
{
    /** @return list<array{id:int,type:string,title:string,slug:string,excerpt:string,url:string}> */
    public function search(string $query, int $limit = 5): array;
}
