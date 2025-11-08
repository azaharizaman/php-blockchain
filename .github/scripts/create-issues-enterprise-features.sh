#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Enterprise Features Implementation
# Usage: ./create-issues-enterprise-features.sh [REPO]
# Example: ./create-issues-enterprise-features.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Enterprise Features"

echo "Creating GitHub issues for Enterprise Features..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement enterprise-grade features: RBAC, multi-tenancy, audit logging, and high-availability patterns" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "enterprise"
    "security"
    "rbac"
    "audit"
    "multi-tenant"
    "high-availability"
    "testing"
    "phase-1"
    "phase-2"
)

for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" 2>/dev/null | grep -q "^$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO" 2>/dev/null || echo "  (label may already exist)"
    fi
done
echo "✓ All required labels ensured"
echo ""

print_issue_header() {
    echo "Creating Issue $1: $2..."
}

# Issue 1: RBAC Implementation
print_issue_header "1" "RBAC Implementation"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Implement RBAC primitives (Role, Permission, PolicyEvaluator)" \
    --milestone "$MILESTONE" \
    --label "enterprise,security,rbac,testing,phase-1" \
    --body "## Overview
Implement role-based access control building blocks for enterprise deployments requiring authorization.

## Requirements
- **REQ-001**: Add RBAC primitives under src/Security/RBAC/
- Role, permission, and policy validators
- Unit tests for role-permission checks

## Implementation Checklist

### 1. Create Role class
- [ ] Create file \`src/Security/RBAC/Role.php\`
- [ ] Add namespace: \`namespace Blockchain\\Security\\RBAC;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Properties: string \$name, array \$permissions
- [ ] Methods: getName(), getPermissions(), hasPermission()

### 2. Create Permission class
- [ ] Create file \`src/Security/RBAC/Permission.php\`
- [ ] Add namespace and strict types
- [ ] Properties: string \$name, ?string \$resource, ?string \$action
- [ ] Methods: getName(), getResource(), getAction(), matches()

### 3. Create PolicyEvaluator
- [ ] Create file \`src/Security/RBAC/PolicyEvaluator.php\`
- [ ] Accept role and permission registries
- [ ] Implement \`public function evaluate(string \$userId, string \$permission, array \$context = []): bool\`
- [ ] Support wildcard permissions (e.g., \"driver.*\")
- [ ] Add phpdoc: \`@param array<string,mixed> \$context\`

### 4. Role registry
- [ ] Implement \`src/Security/RBAC/RoleRegistry.php\`
- [ ] Methods: register(), get(), has(), all()
- [ ] Support hierarchical roles (optional)
- [ ] Load from configuration files

### 5. Permission registry
- [ ] Implement \`src/Security/RBAC/PermissionRegistry.php\`
- [ ] Methods: register(), get(), has(), all()
- [ ] Support permission grouping
- [ ] Load from configuration files

### 6. Integration points
- [ ] Create \`src/Security/RBAC/RBACMiddleware.php\` stub
- [ ] Document integration with BlockchainManager
- [ ] Provide configuration examples
- [ ] Add authentication adapter hooks

### 7. Tests
- [ ] Create \`tests/Security/RBAC/RoleTest.php\`
- [ ] Create \`tests/Security/RBAC/PermissionTest.php\`
- [ ] Create \`tests/Security/RBAC/PolicyEvaluatorTest.php\`
- [ ] Test role-permission assignments
- [ ] Test wildcard permission matching
- [ ] Test context-based evaluation
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Add RBAC guide to \`docs/enterprise/rbac.md\`
- [ ] Explain role and permission concepts
- [ ] Provide configuration examples
- [ ] Document integration patterns

## Acceptance Criteria
- [x] Unit tests validate role-permission checks
- [x] PolicyEvaluator correctly evaluates permissions
- [x] Documentation explains usage

## Files Created
- \`src/Security/RBAC/Role.php\`
- \`src/Security/RBAC/Permission.php\`
- \`src/Security/RBAC/PolicyEvaluator.php\`
- \`src/Security/RBAC/RoleRegistry.php\`
- \`src/Security/RBAC/PermissionRegistry.php\`
- \`tests/Security/RBAC/*Test.php\`
- \`docs/enterprise/rbac.md\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Audit Logging
print_issue_header "2" "Audit Logging"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Add audit logging with file and remote adapters" \
    --milestone "$MILESTONE" \
    --label "enterprise,audit,security,testing,phase-1" \
    --body "## Overview
Implement append-only audit logging for critical operations with redaction support.

## Requirements
- **REQ-003**: Implement audit logging adapters (file + remote)
- **SEC-001**: Apply sensitive data redaction per policy
- Immutable append-only semantics

## Implementation Checklist

### 1. Create AuditLoggerInterface
- [ ] Create file \`src/Audit/AuditLoggerInterface.php\`
- [ ] Add namespace: \`namespace Blockchain\\Audit;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Define method \`public function log(AuditEvent \$event): void;\`

### 2. Create AuditEvent class
- [ ] Create file \`src/Audit/AuditEvent.php\`
- [ ] Properties: timestamp, userId, action, resource, outcome, metadata
- [ ] Methods: toArray(), toJson()
- [ ] Add phpdoc for all properties
- [ ] Make immutable

### 3. Implement FileAuditLogger
- [ ] Create file \`src/Audit/FileAuditLogger.php\`
- [ ] Implement AuditLoggerInterface
- [ ] Append-only file writes
- [ ] Use file locking for concurrent writes
- [ ] Apply SecretRedactor to metadata before writing

### 4. Redaction integration
- [ ] Create \`src/Audit/SecretRedactor.php\`
- [ ] Redact sensitive fields: password, token, privateKey
- [ ] Support custom redaction rules
- [ ] Replace sensitive values with \"[REDACTED]\"
- [ ] Preserve structure for debugging

### 5. Event categories
- [ ] Define event categories: AUTHENTICATION, AUTHORIZATION, TRANSACTION, CONFIG_CHANGE
- [ ] Add severity levels: INFO, WARNING, ERROR, CRITICAL
- [ ] Support event filtering by category
- [ ] Add event type constants

### 6. Remote audit adapter (stub)
- [ ] Create \`src/Audit/RemoteAuditLogger.php\` stub
- [ ] Document expected remote endpoint format
- [ ] Add TLS configuration options
- [ ] Add signing support placeholders
- [ ] Implement basic HTTP POST

### 7. Tests
- [ ] Create \`tests/Audit/FileAuditLoggerTest.php\`
- [ ] Create \`tests/Audit/SecretRedactorTest.php\`
- [ ] Test append-only semantics with concurrent writes
- [ ] Test redaction of sensitive fields
- [ ] Verify log entries are immutable
- [ ] Test log rotation handling
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Add audit guide to \`docs/enterprise/audit.md\`
- [ ] Explain audit event structure
- [ ] Document redaction rules
- [ ] Provide integration examples

## Acceptance Criteria
- [x] Audit logs contain consistent entries
- [x] Entries redacted by SecretRedactor
- [x] Tests pass for concurrent access

## Files Created
- \`src/Audit/AuditLoggerInterface.php\`
- \`src/Audit/AuditEvent.php\`
- \`src/Audit/FileAuditLogger.php\`
- \`src/Audit/SecretRedactor.php\`
- \`src/Audit/RemoteAuditLogger.php\` (stub)
- \`tests/Audit/*Test.php\`
- \`docs/enterprise/audit.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Multi-Tenant Configuration
print_issue_header "3" "Multi-Tenant Configuration"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement multi-tenant configuration support" \
    --milestone "$MILESTONE" \
    --label "enterprise,multi-tenant,testing,phase-1" \
    --body "## Overview
Provide multi-tenant configuration namespace supporting tenant-specific configs and resource isolation.

## Requirements
- **REQ-002**: Multi-tenant configuration support
- Namespaced configs
- Resource isolation hooks

## Implementation Checklist

### 1. Create TenantConfig class
- [ ] Create file \`src/Config/TenantConfig.php\`
- [ ] Add namespace: \`namespace Blockchain\\Config;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Accept tenant ID in constructor

### 2. Configuration storage
- [ ] Implement \`public function getTenantConfig(string \$tenantId): array\`
- [ ] Support file-based tenant configs: config/tenants/{tenantId}.json
- [ ] Support database-backed configs (optional)
- [ ] Cache tenant configs for performance
- [ ] Add phpdoc: \`@return array<string,mixed>\`

### 3. Configuration isolation
- [ ] Ensure tenant configs are isolated
- [ ] Prevent cross-tenant data access
- [ ] Validate tenant ID format
- [ ] Throw exception for invalid/missing tenants

### 4. Tenant context
- [ ] Create \`src/Config/TenantContext.php\` for request-scoped tenant tracking
- [ ] Implement \`public function setCurrentTenant(string \$tenantId): void\`
- [ ] Implement \`public function getCurrentTenant(): ?string\`
- [ ] Thread-safe implementation (consider static storage)

### 5. BlockchainManager integration
- [ ] Add tenant awareness to BlockchainManager
- [ ] Load tenant-specific driver configs
- [ ] Isolate transaction queues per tenant
- [ ] Document multi-tenant usage patterns

### 6. Resource isolation
- [ ] Add tenant ID to all audit log entries
- [ ] Prefix cache keys with tenant ID
- [ ] Separate rate limiters per tenant
- [ ] Document resource isolation guarantees

### 7. Tests
- [ ] Create \`tests/Config/TenantConfigTest.php\`
- [ ] Test tenant config retrieval
- [ ] Test isolation between tenants
- [ ] Test error handling for missing configs
- [ ] Use mocked environment for tests
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Add multi-tenant guide to \`docs/enterprise/multi-tenant.md\`
- [ ] Explain tenant configuration structure
- [ ] Document isolation guarantees
- [ ] Provide deployment examples

## Acceptance Criteria
- [x] Tenant config retrieval works correctly
- [x] Isolation unit tests pass
- [x] Documentation complete

## Files Created
- \`src/Config/TenantConfig.php\`
- \`src/Config/TenantContext.php\`
- \`tests/Config/TenantConfigTest.php\`
- \`docs/enterprise/multi-tenant.md\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: High Availability Guidance
print_issue_header "4" "High Availability Guidance"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Provide HA guidance and optional adapters" \
    --milestone "$MILESTONE" \
    --label "enterprise,high-availability,phase-2" \
    --body "## Overview
Document high-availability patterns and provide optional adapters for persistent queuing and storage.

## Requirements
- HA guidance documentation
- Optional HA adapters for Redis/DB
- Example configurations

## Implementation Checklist

### 1. HA documentation
- [ ] Create \`docs/enterprise/ha.md\`
- [ ] Document deployment patterns: active-passive, active-active
- [ ] Explain load balancing strategies
- [ ] Document failover procedures

### 2. Persistent queue adapter (optional)
- [ ] Create \`src/Operations/RedisTransactionQueue.php\` stub
- [ ] Implement TransactionQueue interface
- [ ] Use Redis lists for queue storage
- [ ] Support distributed locking
- [ ] Add configuration examples

### 3. Distributed cache adapter
- [ ] Create \`src/Cache/RedisCache.php\` implementing CachePool
- [ ] Support cache key namespacing
- [ ] Implement TTL and eviction policies
- [ ] Handle connection failures gracefully

### 4. Database persistence adapter
- [ ] Create \`src/Storage/DatabaseIdempotencyStore.php\`
- [ ] Implement IdempotencyStoreInterface
- [ ] Support multiple DB backends via PDO
- [ ] Add table schema documentation

### 5. Health checks
- [ ] Create \`src/Health/HealthChecker.php\`
- [ ] Check driver connectivity
- [ ] Check cache availability
- [ ] Check queue depth
- [ ] Expose health endpoint format

### 6. Monitoring integration
- [ ] Document metrics to monitor for HA
- [ ] Explain alerting strategies
- [ ] Provide example dashboard configs
- [ ] Document SLA considerations

### 7. Configuration examples
- [ ] Provide Redis cluster configuration
- [ ] Provide database connection pooling config
- [ ] Provide load balancer configuration
- [ ] Document backup and recovery procedures

### 8. Tests
- [ ] Create integration test harness (mocked)
- [ ] Test failover scenarios with mocks
- [ ] Test health check functionality
- [ ] Document test environment setup

## Acceptance Criteria
- [x] HA documentation complete under docs/enterprise/ha.md
- [x] Example configurations provided
- [x] Optional adapters documented

## Files Created
- \`docs/enterprise/ha.md\`
- \`src/Operations/RedisTransactionQueue.php\` (stub)
- \`src/Cache/RedisCache.php\`
- \`src/Storage/DatabaseIdempotencyStore.php\`
- \`src/Health/HealthChecker.php\`
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Remote Audit Forwarding
print_issue_header "5" "Remote Audit Forwarding"
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add audit log forwarding with TLS and signing" \
    --milestone "$MILESTONE" \
    --label "enterprise,audit,security,phase-2" \
    --body "## Overview
Enhance audit logging with remote forwarding capabilities including TLS transport and message signing.

## Requirements
- Forward audit logs to remote sinks
- TLS support
- Message signing support

## Implementation Checklist

### 1. Enhance RemoteAuditLogger
- [ ] Update \`src/Audit/RemoteAuditLogger.php\` from stub to full implementation
- [ ] Accept endpoint URL, TLS config, signing key in constructor
- [ ] Implement batch forwarding
- [ ] Handle network failures with retry

### 2. TLS configuration
- [ ] Support TLS client certificates
- [ ] Support custom CA bundles
- [ ] Validate server certificates
- [ ] Configure TLS version and ciphers
- [ ] Add configuration examples

### 3. Message signing
- [ ] Implement \`src/Audit/MessageSigner.php\`
- [ ] Support HMAC-SHA256 signatures
- [ ] Support RSA signatures (optional)
- [ ] Include signature in HTTP headers
- [ ] Document verification process

### 4. Batch processing
- [ ] Buffer events before sending
- [ ] Configure batch size and timeout
- [ ] Implement exponential backoff on failure
- [ ] Preserve event ordering

### 5. Resilience
- [ ] Local fallback to FileAuditLogger on failure
- [ ] Circuit breaker for repeated failures
- [ ] Dead letter queue for failed events
- [ ] Monitoring hooks for forwarding status

### 6. Remote endpoint format
- [ ] Document expected API format
- [ ] Support JSON and msgpack encoding
- [ ] Add authentication headers
- [ ] Implement rate limiting awareness

### 7. Tests
- [ ] Create \`tests/Audit/RemoteAuditLoggerTest.php\`
- [ ] Create \`tests/Audit/MessageSignerTest.php\`
- [ ] Test with MockHandler for HTTP
- [ ] Test TLS configuration
- [ ] Test signature generation/verification
- [ ] Test batch processing logic
- [ ] Test failure scenarios and fallback
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Update \`docs/enterprise/audit.md\` with remote forwarding
- [ ] Document TLS setup
- [ ] Document signing key management
- [ ] Provide example remote sink implementation

## Acceptance Criteria
- [x] Remote forwarding works in dry-run mode
- [x] Unit tests pass for forwarding logic
- [x] Documentation complete

## Files Created
- Updated \`src/Audit/RemoteAuditLogger.php\`
- \`src/Audit/MessageSigner.php\`
- \`tests/Audit/RemoteAuditLoggerTest.php\`
- \`tests/Audit/MessageSignerTest.php\`
- Updated \`docs/enterprise/audit.md\`
" || echo "⚠ Issue 5 may already exist"

print_issue_header "✓" "All Enterprise Features issues attempted"
echo "Done. Review output above for any warnings about existing issues."
