#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for AP Module Foundation Implementation
# Usage: ./create-issues.sh [REPO]
# Example: ./create-issues.sh azaharizaman/NexusErp

REPO="${1:-azaharizaman/NexusErp}"
MILESTONE="AP Module Foundation"

echo "Creating GitHub issues for AP Module Foundation..."

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement core AP module foundation with payment vouchers, debit notes, and GL integration" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"

# making sure all labels are exist of create new labeh with gh label create if not exist yet
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
   "feature"
   "accounting"
   "ap"
   "database"
   "models"
   "actions"
   "business-logic"
   "filament"
   "ui"
   "api"
   "gl-integration"
   "testing"
   "documentation"
)

# Create labels if they don't exist
for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" | grep -q "$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO"
    fi
done
echo "All required labels are ensured."

# Issue 1: Database Schema & Models
echo "Creating Issue 1: Database Schema & Models..."
gh issue create \
   --repo "$REPO" \
   --title "Implement Database Schema & Models for AP Module Foundation" \
   --milestone "$MILESTONE" \
   --label "feature,accounting,ap,database,models" \
    --body "Implement the core database schema and Eloquent models for the Accounts Payable (AP) Module as specified in the implementation plan.

## Requirements
- **REQ-008**: Must reuse existing SupplierInvoice model from Purchase Module
- **REQ-009**: Must implement HasSerialNumbering trait for transactional models
- **REQ-010**: Must use BCMath for precise financial calculations
- **REQ-011**: Must follow spatie/laravel-model-status for workflow management

## Models to Implement
1. **PaymentVoucher Model**
   - Serial number with AP- prefix
   - Company, supplier relationships
   - Payment method, date, amount tracking
   - Currency and exchange rate support
   - Status workflow (draft, approved, paid, cancelled)
   - GL posting fields
   - Audit trail fields

2. **PaymentVoucherAllocation Model**
   - Links payment vouchers to supplier invoices
   - Tracks allocated amounts
   - Supports partial payments

3. **SupplierDebitNote Model**
   - Serial number with DN- prefix
   - Links to supplier invoices
   - Reason tracking (returns, adjustments)
   - GL integration fields
   - Status workflow

4. **InvoiceMatching Model**
   - Three-way matching support
   - Links PO, GRN, and Invoice
   - Variance tracking and reason codes

## Database Schema
- All monetary fields use DECIMAL(15,4) for precision
- Foreign keys with proper cascade rules
- Composite indexes on (company_id, status)
- Unique constraints on serial numbers
- Timestamps with timezone handling

## Acceptance Criteria
- [ ] All models created with proper relationships
- [ ] Database migrations created and tested
- [ ] HasSerialNumbering trait implemented
- [ ] Spatie model-status integrated
- [ ] Model factories created for testing
- [ ] Unit tests for model relationships
- [ ] Documentation updated

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`
- Database Schema Section: Lines 142-246"

# Issue 2: Business Logic Actions
echo "Creating Issue 2: Business Logic Actions..."
gh issue create \
   --repo "$REPO" \
   --title "Implement Business Logic Actions for AP Payment Processing" \
   --milestone "$MILESTONE" \
   --label "feature,accounting,ap,actions,business-logic" \
    --body "Implement Laravel Actions for AP module business logic using lorisleiva/laravel-actions package.

## Requirements
- **REQ-002**: Implement payment voucher system with batch processing
- **REQ-004**: Implement three-way matching validation
- **REQ-005**: Support payment terms and due date calculation

## Actions to Implement
1. **PostSupplierInvoice Action**
   - Validate invoice data
   - Create journal entries
   - Update GL accounts
   - Handle currency conversion

2. **AllocatePaymentToInvoices Action**
   - Validate allocation amounts
   - Support manual and FIFO allocation
   - Update invoice outstanding amounts
   - Atomic transaction handling

3. **ValidateThreeWayMatch Action**
   - Compare PO, GRN, and Invoice amounts
   - Calculate variances
   - Generate mismatch reports
   - Support tolerance thresholds

4. **ApprovePaymentVoucher Action**
   - Validate approval permissions
   - Change status workflow
   - Create audit trail
   - Trigger notifications

5. **PostPaymentVoucherToGL Action**
   - Create journal entries
   - Update AP accounts
   - Handle exchange rate differences
   - Support batch posting

## Technical Considerations
- Use BCMath for precise calculations
- Database transactions for atomic operations
- Proper error handling and validation
- Event dispatching for notifications

## Acceptance Criteria
- [ ] All actions implemented as invokable classes
- [ ] Actions placed in App\\Actions\\AccountsPayable namespace
- [ ] Unit tests with 90%+ coverage
- [ ] Integration tests for GL posting
- [ ] Documentation for each action
- [ ] Error handling validated

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`
- System Architecture Section: Lines 51-141
- Laravel Actions Guide: \`LARAVEL_ACTIONS_GUIDE.md\`"

# Issue 3: Filament Resources
echo "Creating Issue 3: Filament Resources..."
gh issue create \
   --repo "$REPO" \
   --title "Create Filament Resources for AP Module Management" \
   --milestone "$MILESTONE" \
   --label "feature,accounting,ap,filament,ui" \
    --body "Implement FilamentPHP v4.1 resources for managing payment vouchers, debit notes, and supplier invoice enhancements.

## Requirements
- **REQ-002**: Payment voucher system with AP- prefix
- **REQ-003**: Support supplier debit notes with DN- prefix
- **REQ-007**: Comprehensive audit trails

## Resources to Implement
1. **PaymentVoucherResource**
   - List table with filters and search
   - Create/Edit forms with payment allocation
   - Payment method selection with conditional fields
   - Status badge display
   - Actions for approval and GL posting
   - Audit trail display

2. **SupplierDebitNoteResource**
   - List table with supplier and invoice filters
   - Create/Edit forms with reason selection
   - Link to supplier invoices
   - GL posting actions
   - Status workflow

3. **SupplierInvoiceResource Enhancements**
   - Payment status tracking
   - Outstanding amount display
   - Payment allocation history
   - Three-way matching status

## UI/UX Requirements
- Display meaningful relationship data (supplier names, not IDs)
- Use relationship columns: \`supplier.name\`, \`supplierInvoice.invoice_number\`
- Audit fields showing user names: \`creator.name\`, \`updater.name\`
- Status badges with color coding
- Currency formatting with proper precision
- Validation messages and error states

## Form Schemas
- Follow Filament v4.1 patterns
- Use KeyValue for JSON fields
- Use Select with relationships for IDs
- Proper component imports (Forms\\Components)
- Return type declarations

## Acceptance Criteria
- [ ] All resources created following Filament v4.1 patterns
- [ ] Forms display relationship data correctly
- [ ] Tables have proper filters and search
- [ ] Actions for workflow transitions implemented
- [ ] Status badges with proper styling
- [ ] Audit trail components integrated
- [ ] UI tested with sample data
- [ ] Screenshots documented

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`
- Frontend Architecture Section: Lines 307-393
- Filament Best Practices: \`docs/filament-best-practices.md\`"

# Issue 4: API Endpoints
echo "Creating Issue 4: API Endpoints..."
gh issue create \
   --repo "$REPO" \
   --title "Implement REST API Endpoints for AP Module" \
   --milestone "$MILESTONE" \
   --label "feature,accounting,ap,api" \
    --body "Create RESTful API endpoints for AP module operations with proper authentication and authorization.

## Requirements
- **REQ-006**: Enable GL integration for all AP transactions
- **REQ-017**: Implement payment approval workflow with role-based authorization
- **REQ-018**: Require specific permissions for operations

## Endpoints to Implement
1. **Payment Vouchers**
   - \`GET /api/payment-vouchers\` - List with filtering
   - \`POST /api/payment-vouchers\` - Create new voucher
   - \`GET /api/payment-vouchers/{id}\` - Get details
   - \`PUT /api/payment-vouchers/{id}\` - Update voucher
   - \`POST /api/payment-vouchers/{id}/allocate\` - Allocate to invoices
   - \`POST /api/payment-vouchers/{id}/approve\` - Approve voucher

2. **Supplier Invoices**
   - \`POST /api/supplier-invoices/{id}/post-to-gl\` - Post to GL
   - \`GET /api/supplier-invoices/{id}/payment-history\` - Payment history

3. **Reports**
   - \`GET /api/ap-reports/outstanding\` - Outstanding payables
   - \`GET /api/ap-reports/aging\` - Aging report
   - \`GET /api/ap-reports/payment-forecast\` - Payment forecast

## API Features
- Laravel Sanctum authentication
- Role-based permissions
- Company-scoped data access
- Rate limiting (100 req/min per user)
- Response caching for read operations
- Structured error responses
- Audit logging

## Acceptance Criteria
- [ ] All endpoints implemented with controllers
- [ ] API resources for response formatting
- [ ] Authentication and authorization working
- [ ] Rate limiting configured
- [ ] API documentation generated
- [ ] Integration tests for all endpoints
- [ ] Postman collection created

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`
- API Design Section: Lines 247-306"

# Issue 5: GL Integration
echo "Creating Issue 5: GL Integration..."
gh issue create \
   --repo "$REPO" \
   --title "Implement GL Integration for AP Module Transactions" \
   --milestone "$MILESTONE" \
   --label "feature,accounting,ap,gl-integration" \
    --body "Integrate AP module transactions with the General Ledger system through automated journal entry creation.

## Requirements
- **REQ-006**: Enable GL integration with double-entry bookkeeping
- **REQ-012**: Must integrate with existing JournalEntry and Account models
- **REQ-016**: Support currency conversion and exchange rate handling

## GL Integration Features
1. **Supplier Invoice Posting**
   - Debit expense/asset accounts
   - Credit AP account
   - Handle tax accounts
   - Support multi-currency

2. **Payment Voucher Posting**
   - Debit AP account
   - Credit cash/bank account
   - Handle exchange rate differences
   - Support bank charges

3. **Debit Note Posting**
   - Debit AP account
   - Credit expense/return account
   - Update supplier balance

## Technical Implementation
- Polymorphic relationships with JournalEntry
- Double-entry validation
- Currency conversion with exchange rates
- Batch posting support
- GL posting reversal support

## Acceptance Criteria
- [ ] Journal entries created for all AP transactions
- [ ] Double-entry balancing validated
- [ ] Currency conversion working correctly
- [ ] Exchange rate differences handled
- [ ] GL posting reversal implemented
- [ ] Integration tests with GL module
- [ ] GL reports showing AP transactions correctly

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`
- System Architecture Section: Lines 51-141"

# Issue 6: Testing & Documentation
echo "Creating Issue 6: Testing & Documentation..."
gh issue create \
   --repo "$REPO" \
   --title "Comprehensive Testing & Documentation for AP Module" \
   --milestone "$MILESTONE" \
   --label "testing,documentation,accounting,ap" \
    --body "Create comprehensive test suite and documentation for the AP Module Foundation.

## Requirements
- **REQ-007**: Provide comprehensive audit trails
- **REQ-019**: Maintain audit trail of all approvers
- **REQ-020**: Ensure data validation and sanitization

## Testing Requirements
1. **Unit Tests**
   - Model relationships and scopes
   - Action business logic
   - Calculation accuracy (using BCMath)
   - Validation rules

2. **Feature Tests**
   - Payment voucher creation flow
   - Payment allocation workflow
   - Three-way matching validation
   - GL posting integration
   - API endpoints

3. **Integration Tests**
   - End-to-end payment processing
   - GL integration verification
   - Multi-currency scenarios
   - Approval workflows

## Documentation Requirements
1. **Technical Documentation**
   - Architecture overview
   - Database schema documentation
   - API documentation
   - Action documentation

2. **User Documentation**
   - Payment voucher creation guide
   - Payment allocation guide
   - Debit note processing guide
   - Three-way matching guide

3. **Update Project Files**
   - Update ARCHITECTURAL_DECISIONS.md
   - Update PROGRESS_CHECKLIST.md
   - Update README.md

## Acceptance Criteria
- [ ] Unit tests achieving 90%+ coverage
- [ ] Feature tests for all workflows
- [ ] Integration tests passing
- [ ] API documentation complete
- [ ] User guides created
- [ ] ARCHITECTURAL_DECISIONS.md updated
- [ ] PROGRESS_CHECKLIST.md updated
- [ ] README.md updated with AP module info

## Related Files
- Implementation Plan: \`docs/ways-of-work/plan/ap-module/foundation/implementation-plan.md\`"

echo ""
echo "✅ All GitHub issues created successfully!"
echo ""
echo "Implementation Order:"
echo "1. Database Schema & Models (foundation)"
echo "2. Business Logic Actions (business rules)"
echo "3. GL Integration (critical integration)"
echo "4. Filament Resources (UI layer)"
echo "5. API Endpoints (optional, if API needed)"
echo "6. Testing & Documentation (validation)"
