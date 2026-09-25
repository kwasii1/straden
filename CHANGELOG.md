# Changelog

All notable changes to Straden are documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Release entries are generated automatically by [release-please](https://github.com/googleapis/release-please) from [Conventional Commits](https://www.conventionalcommits.org) — don't edit them by hand.

## Initial development (pre-release)

Summary of what was built before the first tagged release.

### Added

- Browser IDE for k6 scripts with a file explorer, multi-file scripts, uploads, validation and VS Code-style autosave.
- One-click k6 runs with live progress, logs and metrics over websockets, and persisted run history.
- InfluxDB-backed run metrics: latency percentiles, throughput, error rates, response codes, per-endpoint breakdowns and HTTP timing.
- AI test agent for planning and writing scripts, and AI run insight reports, with support for many model providers.
- Connectors for Prometheus, PostgreSQL, MySQL, Redis, MongoDB and Grafana.
- Git repository integration; repositories can be linked to individual tests to scope what the AI agents read.
- MCP server with Sanctum API tokens (`mcp:read`, `mcp:write`, `mcp:run` abilities) for coding agents.
- First-run setup wizard, headless admin bootstrap (`STRADEN_ADMIN_*`) and `straden:admin` recovery command.
- Admin-managed user accounts; instance settings, Horizon and the log viewer are restricted to admins.
- Docker image (FrankenPHP, k6 v2.0.0) and Compose stack with PostgreSQL, Redis and InfluxDB 1.8, automatic HTTPS and same-origin websockets.
- Multi-architecture image publishing to GitHub Container Registry.

### Changed

- Public registration is disabled; admins create user accounts.

### Fixed

- Cancelled runs are recorded as `aborted` instead of `error` (k6 exit code 105).
- New scripts read the target from `__ENV.TARGET_URL` instead of an unreplaced placeholder.
- MongoDB connector connection tests no longer always fail.

