# Cache, job and mail threat model

## Cache

- File cache paths use a hashed key and JSON payloads only; values are never unserialized.
- Cache requires a positive TTL and is stored outside `public/` with restrictive permissions.
- Cache is not an authorization source of truth; invalidation remains explicit application logic.

## Jobs

- File queue payloads contain an allowlisted job name and JSON data, never arbitrary class names.
- Atomic rename claims jobs; idempotency keys suppress duplicate enqueue attempts.
- Failed jobs retain a bounded error message, retry with bounded exponential backoff and finally move
  to `failed/`. Cron invokes `bin/dizge job:work` in bounded batches.
- Queue directories must remain private and monitored; file queues are not a high-throughput daemon.

## Mail

- Mail messages validate recipient and reject newline-bearing sender/subject values to prevent header
  injection.
- `FakeTransport` is required in tests. Production delivery is an adapter concern; credentials stay
  in host configuration, not source control.
