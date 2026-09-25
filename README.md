<p align="center">
  <img src="public/favicon.svg" alt="Straden" width="360">
</p>

<p align="center">
  <strong>Self-hosted load testing, powered by k6 — with AI that writes your scripts and explains your results.</strong>
</p>

<p align="center">
  <a href="https://straden.baidoo.dev">Documentation</a> ·
  <a href="#quick-start">Quick start</a> ·
  <a href="CONTRIBUTING.md">Contributing</a> ·
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

Straden is an open-source platform for running [k6](https://k6.io) load tests on your own infrastructure. Write or generate scripts in a browser IDE, run them with one click, watch results stream in live, and get an AI-written analysis of what's slow and why — without sending your traffic, code or metrics to a third-party SaaS.

<!-- TODO: add a screenshot or short demo GIF of the script editor and run page. -->

## Features

- **Browser IDE for k6 scripts** — Monaco editor with a file explorer, multi-file scripts, drag-and-drop uploads, validation (`k6 inspect`) and autosave.
- **One-click runs with live results** — progress, logs and metrics stream to the browser over websockets. Every run is stored with its summary, thresholds and k6 console output.
- **Rich metrics** — k6 writes time-series data to the bundled InfluxDB: latency percentiles, throughput, error rates, response codes, per-endpoint breakdowns and HTTP timing phases.
- **AI test agent** — describe what you want to test; the agent inspects your connectors, linked repositories and previous runs, proposes a plan and writes the scripts.
- **AI run insights** — a generated report that correlates run metrics with your source code and infrastructure metrics to explain bottlenecks and suggest fixes.
- **Bring your own model** — OpenAI, Anthropic, Gemini, DeepSeek, Mistral, Groq, xAI, OpenRouter, Ollama, Azure OpenAI, AWS Bedrock or any OpenAI-compatible endpoint. Keys are encrypted at rest.
- **Infrastructure connectors** — pull Prometheus, PostgreSQL, MySQL and Redis metrics into the analysis.
- **Git repositories** — connect repositories so the agents can read the code behind the endpoints under test, scoped per test.
- **MCP server** — let coding agents (Claude Code, Cursor, …) create scripts, start runs and read results through a token-authenticated [MCP](https://modelcontextprotocol.io) endpoint.
- **Built for teams** — admin-managed user accounts, two-factor authentication and passkeys.

## Quick start

Requirements: Docker with Compose v2.

```bash
mkdir straden && cd straden
curl -fsSLO https://raw.githubusercontent.com/kwasii1/straden/main/deploy/compose.yaml
curl -fsSL https://raw.githubusercontent.com/kwasii1/straden/main/deploy/.env.example -o .env
docker compose up -d
```

Open <http://localhost:8000> and create the admin account. That's it — k6, PostgreSQL, Redis and InfluxDB all run inside the stack.

To run on a server with automatic HTTPS, set `STRADEN_DOMAIN`, `APP_URL`, `HTTP_PORT=80` and `HTTPS_PORT=443` in `.env`. See the [self-hosting guide](docs/self-hosting.md) for domains, upgrades, backups and configuration.

## How it works

| Service | Role |
|---|---|
| `app` | Web UI and MCP server (FrankenPHP/Caddy); proxies websockets and handles HTTPS |
| `reverb` | Websocket server for live updates |
| `runner` | Queue worker that executes k6 (**v2.0.0**) |
| `scheduler` | Tracks running tests and finalises results |
| `postgres` | Application database |
| `redis` | Cache, sessions and queues |
| `influxdb` | k6 time-series metrics (**InfluxDB 1.8**) |

Straden is built with Laravel, Livewire, Flux and Alpine.js.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for setting up a local environment, running tests and submitting changes.

## Security

Please don't report vulnerabilities in public issues — see [SECURITY.md](SECURITY.md).

## License

Straden is licensed under the [GNU Affero General Public License v3.0](LICENSE). You can use, modify and self-host it freely; if you offer a modified version to others over a network, you must make your changes available under the same license.
