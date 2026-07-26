# Task: TASK-010 — Signed preview URLs and explicit CORS allowlist

## Goal

Add a short-lived, content-bound, revocable preview capability and an explicit public-origin
allowlist without exposing Studio credentials, draft content or broad authenticated CORS.

## Required design

- `DIZGECMS_PREVIEW_SECRET` is mandatory for minting; no configured secret means disabled.
- Preview records contain opaque token hash, content id, expiry, creator and revocation timestamp.
- The URL is HMAC-signed and token-bound; verification uses `hash_equals`.
- Studio minting requires `cms.manage`; public preview is throttled and returns generic 404 on
  invalid, expired or revoked credentials.
- CORS reads origins only from `DIZGECMS_PUBLIC_ORIGINS`; omitted setting means same-origin only.
- Authenticated writes and preview responses never emit wildcard origins or credential sharing.

## Ownership

Migration/repository and preview signing implementation are one atomic security boundary; API
route/CORS response handling and tests are integrated only after that boundary passes review.
