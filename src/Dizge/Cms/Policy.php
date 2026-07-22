<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Security\Identity;

/** Role and ownership rules are explicit so a future team does not change workflow data. */
final class Policy
{
    /** @return list<string> */
    public static function roles(): array { return ['author', 'reviewer', 'publisher', 'admin']; }
    public static function canAccess(Identity $identity): bool { return in_array((string) $identity->attribute('role'), self::roles(), true); }
    public static function canCreate(Identity $identity): bool { return in_array((string) $identity->attribute('role'), ['author', 'admin'], true); }
    /** @param array<string,mixed> $content */
    public static function canEdit(Identity $identity, array $content): bool
    {
        return $identity->attribute('role') === 'admin' || ($identity->attribute('role') === 'author' && (int) $identity->id() === (int) $content['author_id'] && $content['status'] === 'draft');
    }
    /** @param array<string,mixed> $content */
    public static function canView(Identity $identity, array $content): bool
    {
        if (self::canEdit($identity, $content)) return true;
        return self::canTransition($identity, (string) $content['status'], 'approved')
            || self::canTransition($identity, (string) $content['status'], 'draft')
            || self::canTransition($identity, (string) $content['status'], 'published')
            || self::canTransition($identity, (string) $content['status'], 'archived');
    }
    public static function canTransition(Identity $identity, string $from, string $to): bool
    {
        $role = (string) $identity->attribute('role');
        if ($role === 'admin') return Workflow::allows($from, $to);
        return match ($role) {
            'author' => $from === 'draft' && $to === 'review',
            'reviewer' => $from === 'review' && in_array($to, ['approved', 'draft'], true),
            'publisher' => ($from === 'approved' && $to === 'published') || ($from === 'published' && in_array($to, ['archived', 'draft'], true)) || ($from === 'archived' && $to === 'draft'),
            default => false,
        };
    }
    public static function canRestore(Identity $identity): bool { return $identity->attribute('role') === 'admin'; }
    public static function canUpload(Identity $identity): bool { return in_array((string) $identity->attribute('role'), ['author', 'admin'], true); }
}
