# Headless API threat model

## Assets and boundaries

- Public published content, navigation and media delivery metadata.
- Non-public draft/review/approved content and Studio sessions.
- Future preview URLs and their signing secret.
- A same-origin or separately hosted DevinimJS frontend.

## Controls

| Risk | Required control |
| --- | --- |
| Draft leakage | Public queries require `published` plus non-null `published_at`; menus omit stale and non-public targets. |
| Private storage disclosure | API returns `/media/{id}` delivery URLs only, never random storage names or filesystem paths. |
| Browser-held privilege | Starter frontend contains no bearer token, Studio session or signing secret. |
| Broad CORS exposure | Anonymous public reads may receive an explicit allowlist later; authenticated writes and preview endpoints never use `Access-Control-Allow-Origin: *`. |
| Preview replay | Preview URLs must be HMAC-signed, content-bound, purpose-bound and short-lived; signature verification is constant-time. |
| API abuse | Public headless endpoints receive a dedicated route throttle before public launch. |
| HTML misuse by consumer | CMS sanitizes stored HTML; third-party frontends still treat `body_html` as trusted CMS output only, never user-generated arbitrary HTML. |

## Preview contract (implementation follow-up)

Preview access will use an authenticated Studio action that mints a URL containing content id,
expiry and a nonce, signed with an environment-held secret. The API validates all fields before
loading content, returns no token details on failure, and rejects expired or mismatched URLs.
No preview token is stored in browser local storage or reused as an API write credential.

## Regression evidence

`tests/headless_api_security_test.php` proves public repository and navigation resolution cannot
turn an unpublished item into a public API target. TASK-010 will add signed preview tests before
the preview endpoint is enabled.
