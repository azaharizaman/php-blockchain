# Agent Task Format

This directory contains YAML task definitions for the PHP Blockchain agent automation system. Each task defines operations that the agent can perform with appropriate safety guardrails and operator approval requirements.

## Task Definition Format

Tasks are defined in `registry.yaml` with the following structure:

```yaml
tasks:
  task-id:
    id: "task-id"
    name: "Human-readable task name"
    description: "Detailed description of what this task does"
    category: "category-name"  # e.g., generation, testing, documentation, maintenance
    scopes:
      - "scope1"  # Required permissions, e.g., filesystem:write, network:read
      - "scope2"
    safety_flags:
      requires_approval: true  # Whether operator approval is required
      allowed_paths:  # Directories this task is allowed to access
        - "src/"
        - "tests/"
        - "docs/"
      deny_patterns:  # File patterns to deny access to
        - "*.env"
        - "*.key"
        - ".git/*"
    inputs:
      - name: "input_name"
        type: "string"  # string, integer, boolean, array
        required: true
        description: "Input description"
        validation: "regex or validation rule"
    outputs:
      - name: "output_name"
        type: "string"
        description: "Output description"
```

## Task Categories

- **generation**: Tasks that create new code, drivers, or components
- **testing**: Tasks that run or generate tests
- **documentation**: Tasks that update documentation
- **maintenance**: Tasks for code quality, refactoring, or cleanup
- **analysis**: Tasks that analyze code without making changes

## Safety Requirements

### Scopes

Tasks must declare all required scopes:
- `filesystem:read` - Read files from allowed paths
- `filesystem:write` - Write files to allowed paths
- `network:read` - Make network requests (e.g., fetch RPC specs)
- `network:write` - Send data over network
- `database:read` - Read from databases
- `database:write` - Write to databases

### Approval Requirements

Tasks that perform any of the following MUST require operator approval:
- Write to the filesystem
- Make network requests
- Execute external commands
- Modify configuration files
- Install dependencies

### Allowed Paths

Tasks are restricted to specific directories to prevent accidental modification of sensitive files:
- `src/` - Application source code
- `tests/` - Test files
- `docs/` - Documentation
- `.copilot/` - Agent configuration
- `config/` - Configuration files (read-only unless specifically allowed)
- `scripts/` - Build and utility scripts

### Deny Patterns

The following patterns are ALWAYS denied:
- `.env*` - Environment files with secrets
- `*.key`, `*.pem` - Private keys and certificates
- `.git/*` - Git internal files
- `vendor/*` - Dependency files (managed by Composer)
- `node_modules/*` - Node dependencies
- `storage/agent-audit.log` - Audit log (managed by system)

## Creating New Tasks

1. Add task definition to `registry.yaml`
2. Ensure all safety flags are properly configured
3. Document inputs and outputs clearly
4. Test the task with the TaskRegistry loader
5. Verify operator approval workflow works correctly

## Example Task

```yaml
tasks:
  create-driver:
    id: "create-driver"
    name: "Create New Blockchain Driver"
    description: "Scaffold a new blockchain driver with tests and documentation"
    category: "generation"
    scopes:
      - "filesystem:write"
    safety_flags:
      requires_approval: true
      allowed_paths:
        - "src/Drivers/"
        - "tests/Drivers/"
        - "docs/drivers/"
      deny_patterns:
        - "*.env"
        - "*.key"
    inputs:
      - name: "driver_name"
        type: "string"
        required: true
        description: "Name of the blockchain driver (e.g., 'ethereum', 'bitcoin')"
        validation: "^[a-z][a-z0-9_]*$"
      - name: "rpc_url"
        type: "string"
        required: false
        description: "Default RPC URL for the driver"
    outputs:
      - name: "files_created"
        type: "array"
        description: "List of files created by the task"
      - name: "next_steps"
        type: "string"
        description: "Instructions for what to do next"
```

## Audit Trail

All task executions are logged to `storage/agent-audit.log` with:
- Timestamp
- Task ID
- Operator who approved/denied
- Operation outcome
- Files modified
- Any errors or warnings

This ensures full traceability and accountability for all agent operations.

## Available Tasks

### update-readme

**ID**: `update-readme`  
**Category**: Documentation  
**Requires Approval**: Yes

Automatically updates README.md and driver documentation with the latest API surface. Scans `src/Drivers/` directory, extracts driver information, and synchronizes documentation.

**Inputs**:
- `update_type` (optional): Type of update - `drivers`, `api`, `changelog`, or `all` (default: `all`)
- `driver_names` (optional): Array of specific driver names to update
- `template_path` (optional): Custom documentation template path
- `changelog_entry` (optional): Changelog entry to add
- `preview_only` (optional): If true, only show diff preview (default: `false`)

**Features**:
- Automatically discovers drivers in `src/Drivers/`
- Updates README.md's "Supported Blockchains" table
- Generates driver documentation using templates from `docs/templates/`
- Shows diff preview before making changes
- Requires operator approval for all changes

### test-driver

**ID**: `test-driver`  
**Category**: Testing  
**Requires Approval**: No

Runs targeted PHPUnit test suites for blockchain drivers. Supports unit tests, integration tests, or both with detailed pass/fail reporting.

**Inputs**:
- `driver_name` (required): Name of the driver to test (e.g., 'Solana', 'Ethereum')
- `test_type` (optional): `unit`, `integration`, or `all` (default: `all`)
- `coverage` (optional): Generate code coverage report (default: `false`)
- `stop_on_failure` (optional): Stop on first failure (default: `false`)
- `filter` (optional): PHPUnit filter pattern for specific tests
- `verbose` (optional): Enable verbose output (default: `false`)

**Features**:
- Executes PHPUnit with configurable test suites
- Parses test output to extract metrics
- Generates code coverage reports when requested
- Provides detailed failure analysis
- No operator approval needed (read-only operation)

### create-new-driver

**ID**: `create-new-driver`  
**Category**: Generation  
**Requires Approval**: Yes

Scaffolds new blockchain drivers from RPC specifications, generating driver class, tests, and documentation following project conventions.

See `create-new-driver.yaml` for complete specification.

## Usage Examples

```php
use Blockchain\Agent\Tasks\UpdateReadmeTask;
use Blockchain\Agent\Tasks\TestDriverTask;

// Preview documentation updates
$updateTask = new UpdateReadmeTask();
$result = $updateTask->execute([
    'update_type' => 'drivers',
    'preview_only' => true,
]);

// Run driver tests
$testTask = new TestDriverTask();
$result = $testTask->execute([
    'driver_name' => 'Solana',
    'test_type' => 'unit',
    'coverage' => true,
]);
```

See `examples/agent-doc-test-tasks.php` for more examples.
