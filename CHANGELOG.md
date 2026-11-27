# Changelog

All notable changes to `laravel-duckdb` will be documented in this file.

## v2.0.8 - 2026-11-27

### Fixed

- `laravel-duckdb:download-cli` raises PHP's `memory_limit` to `512M` while it runs, so downloading and extracting the CLI doesn't run out of memory. A higher limit or `-1` is left untouched.
- The downloaded `.tar.gz` is now removed from the temp directory after extraction. Before, the archive was still held open and stayed behind.

## v2.0.7 - 2026-11-27

### Fixed

- `laravel-duckdb:download-cli` works again with DuckDB 2.x. Starting with v2.0.0, DuckDB publishes the CLI as `duckdb-cli-<os>-<arch>.tar.gz` instead of `duckdb_cli-<os>-<arch>.zip`. Latest and versions >= 2.0.0 now download the `.tar.gz`; older versions still use the `.zip`.
- `--ver` accepts a version with or without a leading `v`.
- Linux `aarch64` now maps to the `arm64` build, and Alpine Linux gets the `-musl` build.
- A failed download now stops with a clear error instead of trying to extract the error page.
- Downloads use a temporary directory that is cleaned up afterwards, and the command checks that `vendor/bin/duckdb` exists before reporting success.
