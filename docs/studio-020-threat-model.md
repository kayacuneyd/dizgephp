# Studio 0.2 threat model

| Asset / threat | Control |
| --- | --- |
| Preview capability | `DIZGECMS_PREVIEW_SECRET` is mandatory when `DIZGE_ENV=production`; opaque tokens are HMAC-hashed, scoped, expiring and revocable. |
| Block payload injection | The fixed `PageBlocks` schema accepts only bounded data fields and known block types. |
| Reference deletion | References use foreign keys; targets cannot be removed silently. |
| Contact PII disclosure | Submissions expire automatically (default 30 days), list/read access is administrator-only, and every recorded access is audited. |
| Release contamination | Archive builder excludes runtime, secrets, distribution output, VCS, and agent/editor directories; tar/zip membership and checksums are tested. |
