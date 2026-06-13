#!/usr/bin/env bash
# One-time VPS setup for the sourcing brain. Idempotent — safe to re-run.
# Usage: HO_ADMIN_KEY=yourkey bash vps/setup.sh
set -euo pipefail

BASE="${HO_BASE:-https://v2.hoosieronline.com}"
KEY="${HO_ADMIN_KEY:-}"
if [ -z "$KEY" ]; then
    read -rp "Admin key (from the setup page): " KEY
fi
if [ -z "$KEY" ]; then
    echo "No key, no brain. Aborting." >&2
    exit 1
fi

if ! command -v claude >/dev/null 2>&1; then
    echo "claude CLI not found on PATH. Install Claude Code first, then re-run." >&2
    exit 1
fi

# Credentials, readable only by this user.
cat > "$HOME/.ho-agent.env" <<EOF
export HO_BASE="$BASE"
export HO_ADMIN_KEY="$KEY"
EOF
chmod 600 "$HOME/.ho-agent.env"

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
chmod +x "$REPO/vps/run-mission.sh"
mkdir -p "$HOME/ho-agent-logs"

# Verify the key against the live API before installing anything.
echo "Checking the door..."
if ! curl -sf "$BASE/cron.php?job=status&key=$KEY" >/dev/null; then
    echo "Could not reach $BASE/cron.php with that key (403 or network). Fix and re-run." >&2
    exit 1
fi
echo "Door open — key works."

# Daily 6:30am mission (server time). Re-runs replace, never duplicate.
CRON_LINE="30 6 * * * $REPO/vps/run-mission.sh"
( crontab -l 2>/dev/null | grep -v 'vps/run-mission.sh' ; echo "$CRON_LINE" ) | crontab -

echo ""
echo "Installed. The brain hunts every morning at 6:30."
echo "Logs: ~/ho-agent-logs/"
echo "Run one mission right now to test:"
echo "  $REPO/vps/run-mission.sh && tail -n 40 \$(ls -1t ~/ho-agent-logs/* | head -1)"
