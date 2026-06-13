#!/usr/bin/env bash
# Runs the nightly sourcing mission via Claude Code headless mode.
# Installed into cron by vps/setup.sh. Logs to ~/ho-agent-logs/.
set -uo pipefail

ENV_FILE="$HOME/.ho-agent.env"
if [ ! -f "$ENV_FILE" ]; then
    echo "Missing $ENV_FILE — run vps/setup.sh first." >&2
    exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$HOME/ho-agent-logs"
mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/mission-$(date +%F-%H%M).log"

cd "$DIR"
git pull --quiet origin v2 >/dev/null 2>&1 || true

# Previous mission's tail gives tonight's brain yesterday's advice.
PREV="$(ls -1t "$LOG_DIR"/mission-*.log 2>/dev/null | sed -n '2p')"
CONTEXT=""
if [ -n "${PREV:-}" ]; then
    CONTEXT="$(printf '\n\n## Note from the previous mission\n%s\n' "$(tail -n 20 "$PREV")")"
fi

{
    echo "=== HO sourcing mission $(date) ==="
    # Scoped permissions: the mission can search the web, read the repo,
    # write its batch file, and curl the HO API — nothing else, no sudo,
    # no arbitrary shell. The agent cannot email anyone (no send path exists).
    claude -p \
        --allowedTools "WebSearch,WebFetch,Read,Glob,Grep,Write,Bash(curl:*),Bash(cat:*)" \
        "$(cat vps/mission-source.md)${CONTEXT}"
    echo "=== exit: $? $(date) ==="
} >> "$LOG" 2>&1

# Keep the last 30 logs.
ls -1t "$LOG_DIR"/mission-*.log 2>/dev/null | tail -n +31 | xargs -r rm -f
