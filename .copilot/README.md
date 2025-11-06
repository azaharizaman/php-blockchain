# PHP Blockchain Agent — Operator Guide

This repository includes an agent configuration at `.copilot/agent.yml` describing a Copilot-style agent that can help with driver generation, testing, documentation, and repository maintenance.

This short guide explains how to safely operate the agent and what to expect.

## What the agent can do

- Create new blockchain drivers (scaffold + tests + docs)
- Generate or update PHPUnit tests (using Guzzle MockHandler)
- Update `README.md` and per-driver docs
- Run static analysis (PHPStan) and surface suggestions
- Perform lightweight refactors and add small features

## Safety-first defaults

The agent configuration is conservative by default:

- `allow_network_access: false` — the agent will not reach out to external URLs (RPC specs, package registries) unless you explicitly enable it.
- `allow_secrets_exfiltration: false` — the agent will never write or commit secrets, private keys, or `.env` files.
- `allowed_paths` restrict the agent to safe project locations such as `src/`, `tests/`, and `.copilot/`.

This protects private keys and prevents accidental commits of sensitive files.

## How to enable network access (operator)

If you want the agent to fetch external RPC specs or run integration tests, temporarily enable network access in the agent configuration or run the steps locally under your control. Recommended approach:

1. Make a temporary operator-only branch and update `.copilot/agent.yml`:

```yaml
policy:
  allow_network_access: true
```

2. Run agent tasks locally or in a controlled CI environment.
3. Revert the permission change after finishing the task.

Never enable network access in a public repository without verifying inputs and scope.

## How to run the agent (concept)

This repo contains task descriptions the agent can follow. There is no single `copilot` binary included — the config is used to instruct a tool or human operator.

Common operator flows:

- Local development: run `composer test` and `composer analyse` after adding or editing drivers.
- On PR: the workflow `.github/workflows/agent-tasks.yml` runs tests and static analysis automatically.

## Recommended operator commands

Run tests and static analysis locally:

```bash
# install deps
composer install --no-interaction --prefer-dist

# run test suite
composer test

# run static analysis (phpstan)
composer analyse
```

## How to create a new driver safely

1. Use the `create-new-driver` task in `.copilot/agent.yml` as the blueprint.
2. Prefer writing code that uses dependency injection for Guzzle so tests can mock network responses.
3. Add PHPUnit tests that use `GuzzleHttp\Handler\MockHandler` and `HandlerStack`.
4. Register the driver in `src/Registry/DriverRegistry.php`.
5. Add a short driver doc under `docs/drivers/` and update `README.md`.

## CI integration and PRs

This repository adds a GitHub Actions workflow that runs on pull requests and on demand (workflow dispatch). The workflow runs `composer install`, `composer test`, and `composer analyse`.

If you want the agent to perform heavier or networked tasks in CI (for example, fetching RPC specs or running integration tests), only enable those steps behind repository-level protection and review.

## Operator responsibilities

- Keep secrets out of the repository. Use GitHub Secrets or local environment variables for CI.
- Review any generated code (especially code that interacts with external networks) before merging.
- Re-run static analysis and tests after agent-driven changes.

## Notes

- The agent config is intended to be human-reviewable. Treat generated changes like any other contribution and review them in PRs.
- If you need help drafting an RPC-mapping spec for a new driver, ask the agent to scaffold a spec file, then review it before the agent fetches or implements network logic.

---

If you want, I can add example commands to enable temporary operator-mode automation or provide a small script to toggle `allow_network_access` safely. Which would you prefer?