# Contributing to php-blockchain

Thanks for contributing! This guide explains repository conventions, how to work with the agent, run tests, and safely enable optional integration tests.

## Code style & standards

- PHP >= 8.2
- PSR-4 autoloading
- PSR-12 coding standard
- Use strict types and type hints where appropriate

## Running tests locally

```bash
composer install --no-interaction --prefer-dist
composer test
composer analyse
```

Unit tests use PHPUnit. Driver tests should mock network traffic using `GuzzleHttp\Handler\MockHandler` and `HandlerStack`.

## Agent workflows

The repository includes an agent config at `.copilot/agent.yml` and an operator guide at `.copilot/README.md`.

- The agent is conservative by default: `allow_network_access` is set to `false`.
- To temporarily allow the agent to fetch external specs or run networked tasks, use the `scripts/toggle-agent-network.sh` script:

```bash
# enable network access (edits .copilot/agent.yml with a backup)
./scripts/toggle-agent-network.sh enable

# disable network access
./scripts/toggle-agent-network.sh disable
```

Always review generated changes in a pull request before merging.

## CI and integration tests

The GitHub Actions workflow ` .github/workflows/agent-tasks.yml` runs unit tests and static analysis on pull requests.

Integration tests that actually hit blockchains are gated behind a repository secret: `RUN_INTEGRATION_TESTS`. To run integration tests in CI:

1. Add a repository secret `RUN_INTEGRATION_TESTS=true` in GitHub (only enable for trusted runs).
2. Ensure the integration tests are safe (use testnets, cleanup, and do not use production keys).

The workflow will only run integration tests when `RUN_INTEGRATION_TESTS` is set to `true`.

## Adding a new driver

Follow the agent task spec in `.copilot/agent.yml` or:

1. Implement `src/Drivers/{Name}Driver.php` implementing `Blockchain\\Contracts\\BlockchainDriverInterface`.
2. Use dependency injection for the HTTP client so tests can mock network IO.
3. Add tests in `tests/{Name}DriverTest.php` using Guzzle mock handlers.
4. Register the driver in `src/Registry/DriverRegistry.php`.
5. Add a short doc in `docs/drivers/{name}.md` and update `README.md`.

## Security and secrets

- Never commit private keys, wallets, or `.env` files.
- Use GitHub Secrets or your CI provider's secret management for API keys and private configuration.
- The repository `.gitignore` already excludes typical key files and `.env` variants.

## Pull request checklist

- [ ] Fork and branch from `main`
- [ ] Add tests for new behavior
- [ ] Run `composer test` and `composer analyse`
- [ ] Keep changes small and well-scoped
- [ ] Ensure no secrets are added
- [ ] Add or update docs for public APIs

Thanks for improving php-blockchain — contributions are appreciated!