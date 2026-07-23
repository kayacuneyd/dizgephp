<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Explicit content workflow. Role separation can be added without changing stored states. */
final class Workflow
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['review'],
        'review' => ['approved', 'draft'],
        'approved' => ['published', 'draft'],
        'published' => ['archived', 'draft'],
        'archived' => ['draft'],
    ];

    /** @return list<string> */
    public static function statuses(): array { return array_keys(self::TRANSITIONS); }
    /** @return list<string> */
    public static function next(string $status): array { return self::TRANSITIONS[$status] ?? []; }
    public static function allows(string $from, string $to): bool { return in_array($to, self::next($from), true); }
}
