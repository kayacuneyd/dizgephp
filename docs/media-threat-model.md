# DizgeCMS media and retrieval threat model

## Assets

- Private media bytes in `storage/media/`.
- Public, published CMS content and its FTS index.
- SQLite backups in `storage/backups/`.

## Controls

| Threat | Control |
| --- | --- |
| Executable upload / polyglot file | PHP upload error check, byte-level `finfo`, `getimagesize`, strict JPEG/PNG/WebP/GIF allowlist and 5 MB cap. SVG is rejected. |
| Path traversal / filename attack | Random generated filename only; the original name is metadata and never used as a filesystem path. |
| Private filesystem exposure | Media and backup files remain outside `public/`; `/demo/media/{id}` serves only a recorded safe MIME type. |
| Stored XSS via rich editor | Server HTML allowlist runs at save and render; image sources can only be `/demo/media/{id}`. |
| Retrieval data leak | FTS and `/demo/api/context` query only `published` content. The endpoint is rate limited by the trusted remote address. |
| Backup loss | SQLite online snapshot, 14-copy retention and force-gated restore command. Restore is an operator-only maintenance action. |

## Residual risk

- Malware scanning, image re-encoding and object storage are deferred; do not accept office documents or arbitrary binaries.
- The FTS context endpoint is public by design and must never be extended to draft/review content without an authenticated, authorization-aware adapter.
