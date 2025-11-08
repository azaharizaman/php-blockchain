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
