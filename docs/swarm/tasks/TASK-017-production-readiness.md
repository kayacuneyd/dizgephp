# TASK-017 — Production readiness

## Goal

Turn the local staging proof into a repeatable release gate and production operations checklist.

## Ownership

- staging/release operational documentation, cron templates and verification evidence
- no public product-code changes without a separate owner

## Acceptance

- Staging install, backup-restore and upgrade checks are repeatable without production data.
- Scheduled publishing and backups have documented safe cron entries and verification commands.
- Public DNS/TLS staging requirements are ready to apply when DNS authority is supplied.
- Responsive Studio acceptance has reproducible browser checks.
