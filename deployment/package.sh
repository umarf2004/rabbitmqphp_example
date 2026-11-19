#!/usr/bin/env bash
set -euo pipefail

# IT490 Deployment Packaging Script
# Usage: ./deployment/package.sh v1.0.0

VERSION="${1:-}"

if [[ ! "$VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Usage: $0 v1.0.0"
    exit 1
fi

REPO_DIR="$(git rev-parse --show-toplevel)"
RELEASE_DIR="$HOME/it490-deployment/releases"
PACKAGE="$RELEASE_DIR/it490-${VERSION}.tar.gz"

mkdir -p "$RELEASE_DIR"

if [[ -e "$PACKAGE" ]]; then
    echo "ERROR: Package already exists: $PACKAGE"
    exit 1
fi

echo "Packaging IT490 version $VERSION..."

# Package committed files only, excluding sensitive configuration.
git -C "$REPO_DIR" archive --format=tar HEAD \
    | tar --delete \
        rabbitmq.local.ini \
        490project/database.local.ini \
        host.ini \
        local.ini \
        rpc/local.ini \
        testRabbitMQ.ini \
    | gzip > "$PACKAGE"

chmod 600 "$PACKAGE"

echo "Package created successfully!"
echo "Version: $VERSION"
echo "Git commit: $(git -C "$REPO_DIR" rev-parse --short HEAD)"
echo "Package: $PACKAGE"
