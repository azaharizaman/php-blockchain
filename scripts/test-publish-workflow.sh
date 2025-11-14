#!/bin/bash

# TASK-004: Documentation Publishing Workflow - Testing Guide
# This script helps verify the publish-docs workflow configuration

set -e

echo "================================================"
echo "  Documentation Publishing Workflow - Testing"
echo "================================================"
echo ""

# Check if workflow file exists
WORKFLOW_FILE=".github/workflows/publish-docs.yml"
if [ ! -f "$WORKFLOW_FILE" ]; then
    echo "❌ ERROR: Workflow file not found: $WORKFLOW_FILE"
    exit 1
fi
echo "✓ Workflow file exists: $WORKFLOW_FILE"

# Validate YAML syntax
echo ""
echo "Validating YAML syntax..."
if command -v python3 &> /dev/null; then
    python3 -c "import yaml; yaml.safe_load(open('$WORKFLOW_FILE'))" 2>&1
    if [ $? -eq 0 ]; then
        echo "✓ YAML syntax is valid"
    else
        echo "❌ YAML syntax error"
        exit 1
    fi
else
    echo "⚠ Warning: python3 not available, skipping YAML validation"
fi

# Check for required components
echo ""
echo "Checking workflow components..."

components=(
    "workflow_dispatch"
    "environment:"
    "name: documentation"
    "peaceiris/actions-gh-pages"
    "actions/upload-artifact@v4"
    "retention-days: 90"
    "composer run generate-docs"
)

missing=0
for component in "${components[@]}"; do
    if grep -q "$component" "$WORKFLOW_FILE"; then
        echo "✓ Found: $component"
    else
        echo "❌ Missing: $component"
        missing=$((missing + 1))
    fi
done

if [ $missing -gt 0 ]; then
    echo ""
    echo "❌ $missing required component(s) missing"
    exit 1
fi

# Check CONTRIBUTING.md updates
echo ""
echo "Checking CONTRIBUTING.md updates..."
if grep -q "Publishing Documentation" CONTRIBUTING.md; then
    echo "✓ CONTRIBUTING.md includes Publishing Documentation section"
else
    echo "❌ CONTRIBUTING.md missing Publishing Documentation section"
    exit 1
fi

# Check docs/README.md
echo ""
echo "Checking docs/README.md..."
if [ -f "docs/README.md" ]; then
    echo "✓ docs/README.md exists (landing page for published docs)"
else
    echo "⚠ Warning: docs/README.md not found (recommended for GitHub Pages)"
fi

# Summary
echo ""
echo "================================================"
echo "  Test Summary"
echo "================================================"
echo ""
echo "✅ All checks passed!"
echo ""
echo "Next Steps:"
echo "1. Configure GitHub environment 'documentation':"
echo "   - Go to Settings → Environments"
echo "   - Create environment named 'documentation'"
echo "   - Add required reviewers"
echo "   - Set timeout to 24-48 hours"
echo ""
echo "2. Test the workflow:"
echo "   - Go to Actions → Publish Documentation"
echo "   - Click 'Run workflow'"
echo "   - Enter version (e.g., 'latest' or 'v1.0.0')"
echo "   - Verify approval gate activates"
echo ""
echo "3. After approval:"
echo "   - Check docs publish to gh-pages"
echo "   - Verify artifacts are available"
echo "   - Test published URL"
echo ""
echo "Documentation URL (after first publish):"
echo "https://azaharizaman.github.io/php-blockchain"
echo ""
