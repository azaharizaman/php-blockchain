#!/usr/bin/env bash
# Toggle allow_network_access in .copilot/agent.yml
# Usage: ./scripts/toggle-agent-network.sh enable|disable

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
AGENT_YML="$REPO_ROOT/.copilot/agent.yml"
BACKUP="$AGENT_YML.bak.$(date +%s)"

if [ ! -f "$AGENT_YML" ]; then
  echo "Error: $AGENT_YML not found"
  exit 2
fi

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 enable|disable"
  exit 2
fi

cmd="$1"
case "$cmd" in
  enable)
    new_value=true
    ;;
  disable)
    new_value=false
    ;;
  *)
    echo "Invalid arg: $cmd. Use enable or disable."
    exit 2
    ;;
esac

# Create backup
cp "$AGENT_YML" "$BACKUP"

# Use awk to replace the allow_network_access line (works for common YAML formatting)
awk -v nv="$new_value" '
  BEGIN{replaced=0}
  {
    if ($0 ~ /allow_network_access:[[:space:]]*(true|false)/ && replaced==0) {
      sub(/allow_network_access:[[:space:]]*(true|false)/, "allow_network_access: " nv)
      replaced=1
    }
    print $0
  }
  END{
    if (replaced==0) {
      print "# Added by toggle-agent-network.sh";
      print "allow_network_access: " nv
    }
  }
' "$BACKUP" > "$AGENT_YML.tmp" && mv "$AGENT_YML.tmp" "$AGENT_YML"

echo "Updated $AGENT_YML (backup saved to $BACKUP)"
exit 0
