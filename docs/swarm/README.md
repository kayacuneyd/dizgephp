# Controlled Agent Swarm Workspace

This folder holds the durable coordination record for multi-agent work on DizgePHP, following the
[KayaEOS Engineering Constitution](https://github.com/kayacuneyd/kayaengineeringos) and its
[Controlled Agent Swarm playbook](https://github.com/kayacuneyd/kayaengineeringos/blob/main/playbooks/agent-swarm.md).
It is not a chat transcript. Each task has explicit ownership, handoffs, evidence, and a final
human-reviewed merge decision — this project's own `docs/kayaeos-agent-swarm.md` states the same
delivery gate (`bin/dizge verify` + `bin/dizge benchmark --check` before any release).

- `active-work.md` — current task status and owner.
- `tasks/` — task contracts and exclusive file ownership maps.
- `handoffs/` — role outputs and evidence.
- `reviews/` — final review and release recommendations.

## Adaptation note (worktree isolation)

The canonical playbook has each implementer work in a dedicated git branch/worktree. When this
workspace was bootstrapped (2026-07-23), the working tree already carried a large, deliberately
uncommitted set of changes from an earlier session (see project memory) that the maintainer had
not yet approved for a commit checkpoint — branching a worktree off `HEAD` would have silently
excluded that work from every task's base. Isolation was therefore enforced the other way: tasks
were scoped to **fully disjoint file ownership** (verified by the orchestrator before dispatch,
recorded in each task's ownership table) so concurrent implementers can never write the same path,
without requiring a commit this session wasn't authorized to make. Once the maintainer commits a
checkpoint, future swarm tasks should return to the standard worktree-per-task convention.
