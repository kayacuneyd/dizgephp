# TASK-015 — Appearance and editorial teams

## Goal

Complete safe appearance controls and the operating workflow for a small editorial team.

## Ownership

- theme settings/manifest validation and Studio appearance views
- users, password-reset and audit/revision comparison actions/views
- role/workflow policy tests and relevant documentation

## Acceptance

- An administrator can preview and activate an installed theme and set approved visual tokens
  without editing core files.
- Administrators can create, disable and reset users; no account may modify its own authorization
  boundary accidentally.
- Reviewer/publisher workflow, revision comparison and audit history are visible in Studio.

## Security gate

All state-changing routes keep CSRF and explicit authorization; password reset tokens are hashed,
expiring and rate-limited.
