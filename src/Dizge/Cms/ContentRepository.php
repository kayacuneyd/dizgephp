<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Data\Database;

/** Explicit repository for the CMS aggregate. SQL remains fixed and values are always bound. */
final class ContentRepository
{
    public function __construct(private Database $database) {}

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one('SELECT c.*, u.email AS author_email FROM content_items c JOIN users u ON u.id = c.author_id WHERE c.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function publicBySlug(string $type, string $slug): ?array
    {
        return $this->one('SELECT c.*, u.email AS author_email FROM content_items c JOIN users u ON u.id = c.author_id WHERE c.type = ? AND c.slug = ? AND c.status = ? AND c.published_at IS NOT NULL', [$type, $slug, 'published']);
    }

    /** @return array<string,mixed>|null */
    public function redirectBySlug(string $type, string $oldSlug): ?array
    {
        return $this->one('SELECT c.* FROM content_redirects r JOIN content_items c ON c.id = r.content_id WHERE r.type = ? AND r.old_slug = ? AND c.status = ? AND c.published_at IS NOT NULL', [$type, $oldSlug, 'published']);
    }

    /** @return list<array<string, mixed>> */
    public function published(string $type, int $limit = 20): array
    {
        $statement = $this->database->pdo()->prepare('SELECT c.*, u.email AS author_email FROM content_items c JOIN users u ON u.id = c.author_id WHERE c.type = ? AND c.status = ? AND c.published_at IS NOT NULL ORDER BY c.published_at DESC LIMIT ?');
        $statement->bindValue(1, $type);
        $statement->bindValue(2, 'published');
        $statement->bindValue(3, $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function studioList(string $query = '', string $type = '', string $status = ''): array
    {
        $where = [];
        $values = [];
        if ($query !== '') { $where[] = '(c.title LIKE ? OR c.slug LIKE ?)'; $values[] = '%' . $query . '%'; $values[] = '%' . $query . '%'; }
        if (in_array($type, ['page', 'article'], true)) { $where[] = 'c.type = ?'; $values[] = $type; }
        if (in_array($status, Workflow::statuses(), true)) { $where[] = 'c.status = ?'; $values[] = $status; }
        $sql = 'SELECT c.*, u.email AS author_email FROM content_items c JOIN users u ON u.id = c.author_id' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY c.updated_at DESC LIMIT 100';
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($values);
        return $statement->fetchAll();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM content_items WHERE slug = ?' . ($exceptId === null ? '' : ' AND id != ?') . ' LIMIT 1';
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($exceptId === null ? [$slug] : [$slug, $exceptId]);
        return $statement->fetchColumn() !== false;
    }

    /** @return list<array{id:int,type:string,title:string,slug:string,excerpt:string,url:string}> */
    public function searchPublished(string $query, int $limit = 10): array
    {
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $query, $matches);
        $terms = array_slice($matches[0] ?? [], 0, 8);
        if ($terms === []) return [];
        $expression = implode(' AND ', array_map(fn (string $term): string => '"' . str_replace('"', '', $term) . '"*', $terms));
        $statement = $this->database->pdo()->prepare('SELECT c.id, c.type, c.title, c.slug, snippet(content_search, 2, \'<mark>\', \'</mark>\', \'…\', 18) AS excerpt FROM content_search JOIN content_items c ON c.id = content_search.content_id WHERE content_search MATCH ? AND c.status = ? AND c.published_at IS NOT NULL ORDER BY bm25(content_search) LIMIT ?');
        $statement->bindValue(1, $expression);
        $statement->bindValue(2, 'published');
        $statement->bindValue(3, max(1, min(20, $limit)), \PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): array => ['id' => (int) $row['id'], 'type' => (string) $row['type'], 'title' => (string) $row['title'], 'slug' => (string) $row['slug'], 'excerpt' => strip_tags((string) $row['excerpt'], '<mark>'), 'url' => '/demo/' . ((string) $row['type'] === 'page' ? 'pages/' : 'articles/') . (string) $row['slug']], $statement->fetchAll());
    }

    /** @param array{type:string,title:string,slug:string,body_html:string,author_id:int,meta_description?:string} $values */
    public function create(array $values): int
    {
        $now = gmdate('c');
        $this->database->pdo()->beginTransaction();
        try {
            $statement = $this->database->pdo()->prepare('INSERT INTO content_items (type, title, slug, body_html, meta_description, status, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$values['type'], $values['title'], $values['slug'], $values['body_html'], $values['meta_description'] ?? '', 'draft', $values['author_id'], $now, $now]);
            $id = (int) $this->database->pdo()->lastInsertId();
            $this->revision($id, $values['type'], $values['title'], $values['slug'], $values['body_html'], 'draft', $values['author_id']);
            $this->index($id, $values['title'], $values['body_html']);
            $this->event($id, $values['author_id'], 'content.created', null, null, 'draft');
            $this->database->pdo()->commit();
            return $id;
        } catch (\Throwable $exception) {
            $this->database->pdo()->rollBack();
            throw $exception;
        }
    }

    /** @param array{type:string,title:string,slug:string,body_html:string,meta_description?:string} $values */
    public function update(int $id, array $values, int $actorId): void
    {
        $current = $this->find($id) ?? throw new \RuntimeException('Content was not found.');
        $this->database->pdo()->beginTransaction();
        try {
            $this->database->pdo()->prepare('UPDATE content_items SET type = ?, title = ?, slug = ?, body_html = ?, meta_description = ?, updated_at = ? WHERE id = ?')->execute([$values['type'], $values['title'], $values['slug'], $values['body_html'], $values['meta_description'] ?? (string) ($current['meta_description'] ?? ''), gmdate('c'), $id]);
            if ($current['slug'] !== $values['slug']) $this->database->pdo()->prepare('INSERT OR REPLACE INTO content_redirects (type, old_slug, content_id, created_at) VALUES (?, ?, ?, ?)')->execute([(string) $current['type'], (string) $current['slug'], $id, gmdate('c')]);
            $this->revision($id, $values['type'], $values['title'], $values['slug'], $values['body_html'], (string) $current['status'], $actorId);
            $this->index($id, $values['title'], $values['body_html']);
            $this->event($id, $actorId, 'content.saved', null, (string) $current['status'], (string) $current['status']);
            $this->database->pdo()->commit();
        } catch (\Throwable $exception) {
            $this->database->pdo()->rollBack();
            throw $exception;
        }
    }

    public function transition(int $id, string $to, int $actorId, string $note = ''): void
    {
        $current = $this->find($id) ?? throw new \RuntimeException('Content was not found.');
        $from = (string) $current['status'];
        if (!Workflow::allows($from, $to)) throw new \InvalidArgumentException('Unsupported content transition.');
        $publishedAt = $to === 'published' ? gmdate('c') : ($to === 'draft' ? null : $current['published_at']);
        $this->database->pdo()->beginTransaction();
        try {
            $this->database->pdo()->prepare('UPDATE content_items SET status = ?, published_at = ?, updated_at = ? WHERE id = ?')->execute([$to, $publishedAt, gmdate('c'), $id]);
            $this->revision($id, (string) $current['type'], (string) $current['title'], (string) $current['slug'], (string) $current['body_html'], $to, $actorId);
            $this->event($id, $actorId, 'content.transitioned', $note === '' ? null : $note, $from, $to);
            $this->database->pdo()->commit();
        } catch (\Throwable $exception) {
            $this->database->pdo()->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function revisions(int $contentId): array
    {
        $statement = $this->database->pdo()->prepare('SELECT r.*, u.email AS actor_email FROM content_revisions r JOIN users u ON u.id = r.actor_id WHERE r.content_id = ? ORDER BY r.id DESC');
        $statement->execute([$contentId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function events(int $contentId): array
    {
        $statement = $this->database->pdo()->prepare('SELECT e.*, u.email AS actor_email FROM content_events e JOIN users u ON u.id = e.actor_id WHERE e.content_id = ? ORDER BY e.id DESC');
        $statement->execute([$contentId]);
        return $statement->fetchAll();
    }

    public function restoreRevision(int $contentId, int $revisionId, int $actorId): void
    {
        $current = $this->find($contentId) ?? throw new \RuntimeException('Content was not found.');
        $revision = $this->one('SELECT * FROM content_revisions WHERE id = ? AND content_id = ?', [$revisionId, $contentId]) ?? throw new \RuntimeException('Revision was not found.');
        $type = (string) ($revision['type'] ?? $current['type']);
        $this->database->pdo()->beginTransaction();
        try {
            $this->database->pdo()->prepare('UPDATE content_items SET type = ?, title = ?, slug = ?, body_html = ?, status = ?, published_at = ?, updated_at = ? WHERE id = ?')->execute([$type, $revision['title'], $revision['slug'], $revision['body_html'], 'draft', null, gmdate('c'), $contentId]);
            $this->revision($contentId, $type, (string) $revision['title'], (string) $revision['slug'], (string) $revision['body_html'], 'draft', $actorId);
            $this->index($contentId, (string) $revision['title'], (string) $revision['body_html']);
            $this->event($contentId, $actorId, 'content.restored', 'Revision #' . $revisionId . ' restored as draft.', (string) $current['status'], 'draft');
            $this->database->pdo()->commit();
        } catch (\Throwable $exception) {
            $this->database->pdo()->rollBack();
            throw $exception;
        }
    }

    /** @param array{stored_name:string,mime:string,size:int,original_name:string} $media */
    public function createMedia(array $media, int $actorId): int
    {
        $statement = $this->database->pdo()->prepare('INSERT INTO media (stored_name, original_name, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$media['stored_name'], $media['original_name'], $media['mime'], $media['size'], $actorId, gmdate('c')]);
        return (int) $this->database->pdo()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function media(int $id): ?array { return $this->one('SELECT * FROM media WHERE id = ?', [$id]); }

    private function revision(int $contentId, string $type, string $title, string $slug, string $bodyHtml, string $status, int $actorId): void
    {
        $this->database->pdo()->prepare('INSERT INTO content_revisions (content_id, title, slug, body_html, status, actor_id, created_at, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$contentId, $title, $slug, $bodyHtml, $status, $actorId, gmdate('c'), $type]);
    }

    private function index(int $contentId, string $title, string $bodyHtml): void
    {
        try {
            $this->database->pdo()->prepare('DELETE FROM content_search WHERE content_id = ?')->execute([$contentId]);
            $this->database->pdo()->prepare('INSERT INTO content_search (content_id, title, body_html) VALUES (?, ?, ?)')->execute([$contentId, $title, strip_tags($bodyHtml)]);
        } catch (\PDOException $exception) {
            if (!str_contains($exception->getMessage(), 'no such table')) throw $exception;
        }
    }

    private function event(int $contentId, int $actorId, string $event, ?string $note, ?string $from, ?string $to): void
    {
        $this->database->pdo()->prepare('INSERT INTO content_events (content_id, actor_id, event, note, from_status, to_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$contentId, $actorId, $event, $note, $from, $to, gmdate('c')]);
    }

    /** @param list<mixed> $params @return array<string,mixed>|null */
    private function one(string $sql, array $params): ?array
    {
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }
}
