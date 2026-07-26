<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Private image storage: MIME is inspected from bytes; SVG and user filenames never reach rendering. */
final class MediaStorage
{
    /** @var array<string, string> */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new \RuntimeException('Cannot create private media storage.');
    }

    /** @param array<string,mixed> $upload @return array{stored_name:string,mime:string,size:int,original_name:string} */
    public function store(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) throw new \InvalidArgumentException('Select a valid image upload.');
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) throw new \InvalidArgumentException('Images must be between 1 byte and 5 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) throw new \InvalidArgumentException('Only JPEG, PNG, WebP and GIF images are accepted.');
        if (@getimagesize($upload['tmp_name']) === false) throw new \InvalidArgumentException('Uploaded bytes are not a valid image.');
        $stored = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $target = $this->directory . '/' . $stored;
        if (!move_uploaded_file($upload['tmp_name'], $target)) throw new \RuntimeException('The uploaded image could not be stored.');
        chmod($target, 0640);
        return ['stored_name' => $stored, 'mime' => $mime, 'size' => $size, 'original_name' => basename((string) ($upload['name'] ?? 'image'))];
    }

    public function path(string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $storedName)) throw new \InvalidArgumentException('Invalid stored media name.');
        return $this->directory . '/' . $storedName;
    }

    /** Remove a media file only after its database record was proven unreferenced and deleted. */
    public function delete(string $storedName): void
    {
        $path = $this->path($storedName);
        if (is_file($path) && !unlink($path)) throw new \RuntimeException('The unreferenced media file could not be removed.');
    }

    /** @return array{stored_name:string,mime:string,size:int,original_name:string} */
    public function storeBytes(string $bytes, string $originalName): array
    {
        $size = strlen($bytes);
        if ($size < 1 || $size > 5 * 1024 * 1024) throw new \InvalidArgumentException('Imported images must be between 1 byte and 5 MB.');
        $temporary = tempnam($this->directory, 'import-');
        if ($temporary === false || file_put_contents($temporary, $bytes, LOCK_EX) === false) throw new \RuntimeException('Imported image could not be staged.');
        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
            if (!is_string($mime) || !isset(self::TYPES[$mime]) || @getimagesize($temporary) === false) throw new \InvalidArgumentException('Imported bytes are not a supported image.');
            $stored = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
            if (!rename($temporary, $this->directory . '/' . $stored)) throw new \RuntimeException('Imported image could not be stored.');
            chmod($this->directory . '/' . $stored, 0640);
            return ['stored_name' => $stored, 'mime' => $mime, 'size' => $size, 'original_name' => basename($originalName)];
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
