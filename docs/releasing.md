# Release process

1. Confirm `bin/dizge verify` and `bin/dizge benchmark --check` pass.
2. Review `bin/dizge inspect --markdown`, migration order and public documentation.
3. Commit with the maintainer’s Git identity only; do not add automated co-author trailers.
4. Create an annotated `vX.Y.Z` tag, push `main` and the tag, then publish a GitHub Release with a
   generated source ZIP.
5. Verify the tagged release installation on PHP 8.1+ and a `public/` document root.

The first public release is `v0.1.0`. Composer packaging is deliberately deferred; GitHub Releases
provide the dependency-free ZIP channel required by shared hosting.
