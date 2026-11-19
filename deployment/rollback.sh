#!/usr/bin/env bash
set -euo pipefail

# IT490 Deployment Rollback Script
# Usage: ./deployment/rollback.sh v0.0.1

VERSION="${1:-}"

if [[ ! "$VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 v0.0.1"
    exit 1
fi

BASE_DIR="$HOME/it490-deployment"
INSTALL_DIR="$BASE_DIR/installed"
TARGET="$INSTALL_DIR/$VERSION"
CURRENT="$BASE_DIR/current"
HISTORY="$BASE_DIR/deployment-history.log"

# Check that the requested version exists.
if [[ ! -d "$TARGET" ]]; then
    echo "ERROR: Version $VERSION is not installed."
    exit 1
fi

# Check that an active release exists.
if [[ ! -L "$CURRENT" ]]; then
    echo "ERROR: No active deployment found."
    exit 1
fi

# Get the currently active release.
PREVIOUS="$(readlink "$CURRENT")"

if [[ "$PREVIOUS" == "$TARGET" ]]; then
    echo "Version $VERSION is already active."
    exit 0
fi

echo "Rolling back IT490 application..."
echo "Current release: $PREVIOUS"
echo "Target release: $TARGET"

# Switch the active release atomically.
TEMP_LINK="$BASE_DIR/.rollback-next"

ln -s "$TARGET" "$TEMP_LINK"
mv -Tf "$TEMP_LINK" "$CURRENT"

# Record the rollback.
printf '%s | action=rollback | version=%s | previous=%s\n' \
    "$(date -Is)" "$VERSION" "$PREVIOUS" >> "$HISTORY"

echo "Rollback successful!"
echo "Active version: $VERSION"
echo "Previous release: $PREVIOUS"
