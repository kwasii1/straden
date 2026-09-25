# Contributing to Straden

Thanks for your interest in improving Straden! Bug reports, fixes, docs and features are all welcome. By participating you agree to follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- **Bugs** — search [existing issues](https://github.com/kwasii1/straden/issues) first. A good report includes what you did, what you expected, what happened, your Straden version and relevant logs (`docker compose logs app runner`).
- **Features and larger changes** — please open an issue to discuss the idea before writing code, so we can agree on the approach and avoid wasted effort.
- **Security issues** — never open a public issue. Follow [SECURITY.md](SECURITY.md).

## Development setup

### Requirements

- PHP 8.4 with the `pdo_sqlite`/`pdo_pgsql`, `redis`, `pcntl`, `posix` and `intl` extensions
- Composer 2
- Node.js 24 and npm
- [k6 v2.0.0](https://github.com/grafana/k6/releases/tag/v2.0.0) on your `PATH`
- Redis
- InfluxDB 1.8 (for run metrics — e.g. `docker run -d -p 8086:8086 -e INFLUXDB_DB=k6 influxdb:1.8`)

### Install and run

```bash
git clone https://github.com/kwasii1/straden.git
cd straden
composer setup     # installs dependencies, creates .env, generates a key, migrates, builds assets
composer dev       # starts the web server, queue (Horizon), scheduler, Reverb and Vite
```

The default `.env` uses SQLite. Open the app and the setup wizard will ask you to create the first admin account, or seed a test admin (`test@example.com` / `password`) with `php artisan db:seed`.

### Working on the Docker image

```bash
cd deploy
docker compose -f compose.yaml -f compose.build.yaml up -d --build
```

## Coding standards

- Follow the conventions of the surrounding code — check sibling files for structure and naming.
- PHP is formatted with [Pint](https://laravel.com/docs/pint) and analysed with PHPStan:
  ```bash
  composer lint          # format
  composer types:check   # static analysis
  ```
- Livewire pages are single-file components (`resources/views/pages/**/⚡*.blade.php`); UI uses Flux components and Tailwind, with Alpine.js for client-side behaviour.
- Keep dependencies minimal. Discuss new packages in an issue first.

## Tests

Straden uses [Pest](https://pestphp.com). Every behaviour change should come with a test.

```bash
php artisan test --compact                         # full suite
php artisan test --compact --filter="run insight"  # a subset
composer test                                      # lint check + static analysis + tests (what CI runs)
```

Tests run against in-memory SQLite and never touch your development database.

## Pull requests

1. Fork the repository and create a branch from `main` (e.g. `fix/run-cancel-status`).
2. Make focused commits using [Conventional Commits](https://www.conventionalcommits.org) — see [Releases](#releases) for why this matters.
3. Add or update tests, and run `composer test` locally.
4. Open a pull request describing **what** changed and **why**, with screenshots for UI changes, and link the related issue. Use a Conventional Commit style PR title (e.g. `fix: record cancelled runs as aborted`) — it becomes the commit message when the PR is squash-merged.

A maintainer will review as soon as possible. Small, focused PRs are reviewed fastest.

## Releases

Releases are fully automated with [release-please](https://github.com/googleapis/release-please); don't edit `CHANGELOG.md` or version numbers by hand.

1. Every merge to `main` runs the test suite and publishes `ghcr.io/kwasii1/straden:latest`.
2. release-please keeps a **`chore(main): release X.Y.Z`** pull request open, with the next version and changelog worked out from commit messages:
   - `fix:` → patch release, `feat:` → minor release
   - `feat!:` or a `BREAKING CHANGE:` footer → major release (minor while pre-1.0)
   - `docs:`, `test:`, `refactor:`, `chore:` → no release on their own
3. Merging the release PR tags `vX.Y.Z`, publishes a GitHub Release with the changelog, and pushes the image tagged `X.Y.Z`, `X.Y` and `latest`.

## License

By contributing, you agree that your contributions will be licensed under the project's [AGPL-3.0 license](LICENSE).
