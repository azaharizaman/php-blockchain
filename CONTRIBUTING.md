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

## Code quality checks

This project enforces code quality through static analysis and code style checks:

### PHPStan (Static Analysis)

PHPStan analyzes code for type errors and potential bugs at level 7.

```bash
# Run PHPStan
composer run phpstan

# Or use the analyse alias
composer run analyse
```

### PHP_CodeSniffer (Code Style)

PHPCS enforces PSR-12 coding standards.

```bash
# Check code style
composer run lint

# Auto-fix code style issues
composer run fix
```

### Running all checks

Before committing, run all quality checks:

```bash
composer test      # Run tests
composer phpstan   # Run static analysis
composer lint      # Check code style
```

All these checks are automatically run in CI on pull requests.

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

## Documentation Generation

The repository includes automated documentation generation and checking tools for driver documentation.

### Generating Driver Documentation

To generate documentation stubs for new drivers:

```bash
composer run generate-docs
```

This script:
- Scans `src/Drivers/` for driver classes
- Generates documentation in `docs/drivers/{driver}.md` using the template
- Skips existing documentation files (won't overwrite your work)
- Extracts methods from `BlockchainDriverInterface`

### Checking Driver Documentation

To verify all drivers have complete documentation:

```bash
composer run check-docs
```

This script:
- Verifies each driver has corresponding documentation
- Checks for required sections (Overview, Installation)
- Reports missing or incomplete documentation
- Exits with error code if checks fail (useful for CI)

### Documentation Templates

Driver documentation templates are located at:
- `docs/templates/driver-template.md` - Template for new drivers
- `docs/templates/driver-readme.md` - Alternative template

### Updating Templates

When you need to update the documentation template:

1. Edit `docs/templates/driver-template.md`
2. Add or modify template placeholders (e.g., `{{driver_name}}`, `{{blockchain_name}}`)
3. Update the `generateFromTemplate()` function in `scripts/generate-driver-docs.php` if adding new placeholders
4. Manually update existing driver docs to match the new structure (generator won't overwrite existing files)

### When to Run Generator vs Checker

- **Run generator**: When adding a new driver and you want a quick documentation stub
- **Run checker**: Before committing, in CI, or when reviewing PRs to ensure all drivers are documented
- **Manual editing**: After running the generator, always edit the generated file to add driver-specific details like:
  - Actual RPC endpoints
  - Network-specific configuration
  - Real usage examples
  - Links to official documentation

### Required Documentation Sections

All driver documentation must include:
- **Overview**: What the driver does, key features
- **Installation**: How to install the package
- Additional recommended sections: Usage, Configuration, Examples, Testing

## Security and secrets

- Never commit private keys, wallets, or `.env` files.
- Use GitHub Secrets or your CI provider's secret management for API keys and private configuration.
- The repository `.gitignore` already excludes typical key files and `.env` variants.

## Pull request checklist

- [ ] Fork and branch from `main`
- [ ] Add tests for new behavior
- [ ] Run `composer test` and `composer analyse`
- [ ] Run `composer lint` to check code style (use `composer fix` to auto-fix)
- [ ] Keep changes small and well-scoped
- [ ] Ensure no secrets are added
- [ ] Add or update docs for public APIs

Thanks for improving php-blockchain — contributions are appreciated!