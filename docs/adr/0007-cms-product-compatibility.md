# ADR-0007: DizgeCMS ürün ve WordPress uyumluluk sınırı

- Status: Accepted
- Date: 2026-07-24

## Context

DizgeCMS is intended to be an open-source, extendable CMS with a WordPress-like editorial
experience. Running arbitrary WordPress plugins or themes would require a compatibility runtime,
sandboxing and a larger security surface than the shared-hosting product can safely guarantee.

## Decision

DizgeCMS provides a native Page/Article/Media model, role-based workflow, local allowlisted
plugins/themes, WXR import, public permalink redirects, metadata preservation and a scoped
content REST API. WordPress plugin and theme execution is explicitly not supported.

The reference demo paths remain available as compatibility aliases while studio, admin, pages and
articles are the product-facing paths.

## Consequences

Migration from WordPress is practical without pretending that untrusted WordPress PHP can run
inside DizgeCMS. Plugin and theme contracts remain smaller, reviewable and compatible with the
build-free PHP deployment model.
