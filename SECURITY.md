# Security Policy

Straden stores sensitive data — AI provider API keys, database and Redis connector credentials, Git provider tokens and MCP API tokens — and executes user-supplied k6 scripts. We take vulnerabilities seriously and appreciate responsible disclosure.

## Reporting a vulnerability

**Please do not report security issues in public GitHub issues, discussions or pull requests.**

Report privately through GitHub's [private vulnerability reporting](https://github.com/kwasii1/straden/security/advisories/new) (the **Security → Report a vulnerability** button on the repository).

Please include:

- A description of the issue and its impact
- Steps to reproduce, or a proof of concept
- The affected version (image tag or commit) and deployment details
- Any suggested fix or mitigation

## What to expect

- Acknowledgement within **3 business days**.
- An initial assessment and severity estimate within **7 days**.
- Updates as we work on a fix, and credit in the advisory and release notes if you'd like it.

Please give us a reasonable amount of time to release a fix before any public disclosure. We won't take legal action against good-faith research that follows this policy, avoids privacy violations and service disruption, and only tests against instances you own.

## Supported versions

Security fixes are released for the latest minor version. Please keep your deployment up to date:

```bash
docker compose pull && docker compose up -d
```

| Version | Supported |
|---|---|
| Latest release | ✅ |
| Older releases | ❌ |

## Scope

In scope: the Straden application, its Docker image and the deployment files in this repository.

Out of scope: vulnerabilities in third-party dependencies that are already publicly known (please report those upstream), issues requiring a compromised host or admin account, and load generated against systems you don't own — Straden is a load-testing tool, and pointing it at third-party systems without permission is the operator's responsibility.

## Hardening tips for operators

- Serve Straden over HTTPS (set `STRADEN_DOMAIN`) and don't expose PostgreSQL, Redis or InfluxDB publicly — the provided `compose.yaml` keeps them on the internal network.
- Back up the `storage` volume: it contains the generated `APP_KEY` that encrypts stored credentials.
- Give MCP tokens only the abilities they need and set an expiry.
- Enable two-factor authentication for admin accounts.
