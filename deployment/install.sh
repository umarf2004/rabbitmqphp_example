#!/usr/bin/env bash
set -euo pipefail

# IT490 Deployment Installer
# Usage: ./deployment/install.sh v0.0.1
#
# Installs a release locally without touching running services.

VERSION="${1:-}"

if [[ ! "$VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Usage: $0 v1.0.0"
    exit 1
fi

BASE_DIR="$HOME/it490-deployment"
PACKAGE="$BASE_DIR/releases/it490-${VERSION}.tar.gz"
INSTALL_DIR="$BASE_DIR/installed"
TARGET="$INSTALL_DIR/$VERSION"
CURRENT="$BASE_DIR/current"
HISTORY="$BASE_DIR/deployment-history.log"

if [[ ! -f "$PACKAGE" ]]; then
    echo "ERROR: Package not found: $PACKAGE"
    exit 1
fi

if [[ -e "$TARGET" ]]; then
    echo "ERROR: Version $VERSION is already installed."
    exit 1
fi

mkdir -p "$INSTALL_DIR"
mkdir -p "$TARGET"

echo "Installing IT490 release $VERSION..."

if ! tar -xzf "$PACKAGE" -C "$TARGET"; then
    echo "ERROR: Package extraction failed."
    rm -rf -- "$TARGET"
    exit 1
fi

# Track the previous installed version.
PREVIOUS="none"

if [[ -L "$CURRENT" ]]; then
    PREVIOUS="$(readlink "$CURRENT")"
elif [[ -e "$CURRENT" ]]; then
    echo "ERROR: $CURRENT exists and is not a symbolic link."
    exit 1
fi

# Activate the new release through an atomic symlink switch.
ln -s "$TARGET" "$BASE_DIR/.current-next"
mv -Tf "$BASE_DIR/.current-next" "$CURRENT"

# Record the deployment.
printf '%s | version=%s | previous=%s\n' \
    "$(date -Is)" "$VERSION" "$PREVIOUS" >> "$HISTORY"

echo "Installation successful!"
echo "Version: $VERSION"
echo "Installed at: $TARGET"
echo "Active release: $CURRENT"
echo "Previous release: $PREVIOUS"
