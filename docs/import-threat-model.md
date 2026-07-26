# DizgeCMS WXR import threat model

## Boundary

WXR is treated as untrusted XML. The importer reads content fields and never evaluates WordPress
PHP, plugin code or theme code.

## Controls

- XML parsing uses LIBXML_NONET and rejects unreadable or malformed items.
- Only post and page items become content; unsupported post types are skipped.
- Imported HTML passes through the existing CMS sanitizer before normal publishing.
- Slugs are normalized and collisions receive a safe native suffix plus a redirect record.
- Metadata is copied only from an explicit description-key allowlist.
- Media downloading is opt-in, HTTPS-only, timeout-bounded and limited to 5 MB.
- Media bytes are inspected by MIME and image parsers; SVG and executable content are rejected.
- Dry-run performs parsing and validation without writing content or media.

## Residual risk

Remote media hosts can still be unavailable or slow; production imports should be run from a
maintenance shell with a backup and dry-run report first.
