# KayaEOS agent-swarm protocol

## Roles

- **Architecture lead:** owns public API, ADRs, integration and release decision.
- **Domain/API agent:** drafts route, action, data and response contracts.
- **Security agent:** writes threat model and regression cases before sensitive code merges.
- **Performance agent:** defines benchmark metric/baseline and investigates regressions.
- **Implementation agent:** changes only its assigned module boundary.
- **Verification agent:** runs test, static and deployment checks; it does not redesign the module.

## Delivery gate

Every change follows: intent and acceptance criteria → ADR/threat model when applicable → explicit
contract → isolated implementation → tests and benchmark → independent review → `bin/dizge verify`
and `bin/dizge benchmark --check` → project memory update. Agents do not deploy directly to
production or write simultaneously into the same concern.

The benchmark gate compares the median of five local samples to the checked-in target-host median;
each metric may regress by at most 15%. Refreshing a baseline requires a documented target-host
measurement, not a one-off fast run.

`bin/dizge inspect --json` is the agent entrypoint. It supplies route, migration, component, module
and verification context without forcing broad repository scans.
