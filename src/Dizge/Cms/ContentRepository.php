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

    public function createRedirect(string $type, string $oldSlug, int $contentId): void
    {
        if (!in_array($type, ['page', 'article', 'service'], true) || $oldSlug === '') throw new \InvalidArgumentException('Redirect type and slug are required.');
        $this->database->pdo()->prepare('INSERT OR REPLACE INTO content_redirects (type, old_slug, content_id, created_at) VALUES (?, ?, ?, ?)')->execute([$type, $oldSlug, $contentId, gmdate('c')]);
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
        if (in_array($type, ['page', 'article', 'service'], true)) { $where[] = 'c.type = ?'; $values[] = $type; }
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
        return array_map(fn (array $row): array => ['id' => (int) $row['id'], 'type' => (string) $row['type'], 'title' => (string) $row['title'], 'slug' => (string) $row['slug'], 'excerpt' => strip_tags((string) $row['excerpt'], '<mark>'), 'url' => '/' . match ((string) $row['type']) { 'page' => 'pages/', 'service' => 'services/', default => 'articles/' } . (string) $row['slug']], $statement->fetchAll());
    }

    /** @param array{type:string,title:string,slug:string,body_html:string,author_id:int,meta_description?:string,status?:string,published_at?:string,created_at?:string,updated_at?:string} $values */
    public function create(array $values): int
    {
        $now = gmdate('c');
        $status = (string) ($values['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published'], true)) throw new \InvalidArgumentException('Content creation only permits draft or published status.');
        $createdAt = (string) ($values['created_at'] ?? $now);
        $updatedAt = (string) ($values['updated_at'] ?? $createdAt);
        $publishedAt = $status === 'published' ? (string) ($values['published_at'] ?? $createdAt) : null;
        $this->database->pdo()->beginTransaction();
        try {
            if (!in_array($values['type'], ['page', 'article', 'service'], true)) throw new \InvalidArgumentException('Unsupported content type.');
            $blocksJson = (string) ($values['blocks_json'] ?? '[]'); (new PageBlocks())->decode($blocksJson);
            $statement = $this->database->pdo()->prepare('INSERT INTO content_items (type, title, slug, body_html, meta_description, status, author_id, created_at, updated_at, blocks_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$values['type'], $values['title'], $values['slug'], $values['body_html'], $values['meta_description'] ?? '', $status, $values['author_id'], $createdAt, $updatedAt, $blocksJson]);
            $id = (int) $this->database->pdo()->lastInsertId();
            if ($publishedAt !== null) $this->database->pdo()->prepare('UPDATE content_items SET published_at = ? WHERE id = ?')->execute([$publishedAt, $id]);
            $this->revision($id, $values['type'], $values['title'], $values['slug'], $values['body_html'], $status, $values['author_id'], $blocksJson);
            $this->index($id, $values['title'], $values['body_html']);
            $this->event($id, $values['author_id'], 'content.created', null, null, $status);
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
            if (!in_array($values['type'], ['page', 'article', 'service'], true)) throw new \InvalidArgumentException('Unsupported content type.');
            $blocksJson = (string) ($values['blocks_json'] ?? ($current['blocks_json'] ?? '[]')); (new PageBlocks())->decode($blocksJson);
            $this->database->pdo()->prepare('UPDATE content_items SET type = ?, title = ?, slug = ?, body_html = ?, meta_description = ?, blocks_json = ?, updated_at = ? WHERE id = ?')->execute([$values['type'], $values['title'], $values['slug'], $values['body_html'], $values['meta_description'] ?? (string) ($current['meta_description'] ?? ''), $blocksJson, gmdate('c'), $id]);
            if ($current['slug'] !== $values['slug']) $this->database->pdo()->prepare('INSERT OR REPLACE INTO content_redirects (type, old_slug, content_id, created_at) VALUES (?, ?, ?, ?)')->execute([(string) $current['type'], (string) $current['slug'], $id, gmdate('c')]);
            $this->revision($id, $values['type'], $values['title'], $values['slug'], $values['body_html'], (string) $current['status'], $actorId, $blocksJson);
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
            $this->revision($id, (string) $current['type'], (string) $current['title'], (string) $current['slug'], (string) $current['body_html'], $to, $actorId, (string) ($current['blocks_json'] ?? '[]'));
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
            $blocksJson = (string) ($revision['blocks_json'] ?? '[]');
            $this->database->pdo()->prepare('UPDATE content_items SET type = ?, title = ?, slug = ?, body_html = ?, blocks_json = ?, status = ?, published_at = ?, updated_at = ? WHERE id = ?')->execute([$type, $revision['title'], $revision['slug'], $revision['body_html'], $blocksJson, 'draft', null, gmdate('c'), $contentId]);
            $this->revision($contentId, $type, (string) $revision['title'], (string) $revision['slug'], (string) $revision['body_html'], 'draft', $actorId, $blocksJson);
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

    /** @return list<array<string,mixed>> */
    public function mediaList(int $limit = 100, string $query = ''): array
    {
        $query = trim($query);
        $sql = 'SELECT * FROM media';
        if ($query !== '') $sql .= ' WHERE original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ?';
        $sql .= ' ORDER BY id DESC LIMIT ?';
        $statement = $this->database->pdo()->prepare($sql);
        $index = 1;
        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            foreach ([$like, $like, $like] as $value) $statement->bindValue($index++, $value, \PDO::PARAM_STR);
        }
        $statement->bindValue($index, max(1, min(200, $limit)), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function updateMediaMetadata(int $id, string $altText, string $caption): bool
    {
        if (mb_strlen($altText) > 160 || mb_strlen($caption) > 500) throw new \InvalidArgumentException('Media text is too long.');
        $statement = $this->database->pdo()->prepare('UPDATE media SET alt_text = ?, caption = ? WHERE id = ?');
        $statement->execute([trim($altText), trim($caption), $id]); return $statement->rowCount() === 1;
    }

    public function setFeaturedMedia(int $contentId, ?int $mediaId): bool
    {
        if ($mediaId !== null && $this->media($mediaId) === null) throw new \InvalidArgumentException('Selected media was not found.');
        $statement = $this->database->pdo()->prepare('UPDATE content_items SET featured_media_id = ?, updated_at = ? WHERE id = ?');
        $statement->execute([$mediaId, gmdate('c'), $contentId]); return $statement->rowCount() === 1;
    }

    /** Deletes only unreferenced media, never silently breaking published or draft content. */
    public function deleteMediaIfUnused(int $id): bool
    {
        if ($id < 1) return false;
        $references = $this->database->pdo()->prepare("SELECT (SELECT COUNT(*) FROM content_items WHERE featured_media_id = ?) + (SELECT COUNT(*) FROM content_items WHERE body_html LIKE ? ESCAPE '\\')");
        $references->execute([$id, '%/media/' . $id . '%']);
        if ((int) $references->fetchColumn() > 0) throw new \InvalidArgumentException('This media is still used by content and cannot be deleted.');
        $statement = $this->database->pdo()->prepare('DELETE FROM media WHERE id = ?');
        $statement->execute([$id]);
        return $statement->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM content_items WHERE id = ?');
        $statement->execute([$id]);
        return $statement->rowCount() === 1;
    }

    /** @param list<int> $targetIds */
    public function replaceReferences(int $contentId, array $targetIds): void
    {
        $targetIds = array_values(array_unique(array_filter($targetIds, fn (mixed $id): bool => is_int($id) && $id > 0 && $id !== $contentId)));
        $this->database->transaction(function () use ($contentId, $targetIds): void {
            $this->database->pdo()->prepare('DELETE FROM content_references WHERE source_content_id = ?')->execute([$contentId]);
            $insert = $this->database->pdo()->prepare('INSERT INTO content_references (source_content_id, target_content_id, created_at) VALUES (?, ?, ?)');
            foreach ($targetIds as $targetId) { if ($this->find($targetId) === null) throw new \InvalidArgumentException('Referenced content was not found.'); $insert->execute([$contentId, $targetId, gmdate('c')]); }
        });
    }

    /** @return list<array<string,mixed>> */
    public function referencesFrom(int $contentId): array
    {
        $statement = $this->database->pdo()->prepare('SELECT c.* FROM content_references r JOIN content_items c ON c.id = r.target_content_id WHERE r.source_content_id = ? ORDER BY c.title');
        $statement->execute([$contentId]); return $statement->fetchAll();
    }

    private function revision(int $contentId, string $type, string $title, string $slug, string $bodyHtml, string $status, int $actorId, string $blocksJson = '[]'): void
    {
        $this->database->pdo()->prepare('INSERT INTO content_revisions (content_id, title, slug, body_html, status, actor_id, created_at, type, blocks_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$contentId, $title, $slug, $bodyHtml, $status, $actorId, gmdate('c'), $type, $blocksJson]);
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
