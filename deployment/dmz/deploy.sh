#!/bin/bash

# Stop the script if a command fails
set -euo pipefail

# Git repository location
REPO_DIR="$HOME/git/rabbitmqphp_example"

# Where the workers are installed
MASSIVE_DIR="/opt/it490/massive-worker"
EMAIL_DIR="/opt/it490/email-worker"

# Where deployment backups are stored
BACKUP_DIR="/opt/it490/deployments/dmz/backups"

echo "IT490 DMZ Deployment System"
echo "---------------------------"

# Check that the repository exists
if [ ! -d "$REPO_DIR/.git" ]; then
    echo "ERROR: Git repository not found."
    exit 1
fi

# Display the current Git version
cd "$REPO_DIR"

# Use the commit provided by the user, or HEAD by default
VERSION="${1:-HEAD}"

# Check whether dry-run mode was requested
DRY_RUN=false

if [ "${2:-}" = "--dry-run" ]; then
    DRY_RUN=true
fi

# Check whether the requested Git commit exists
if ! git rev-parse --verify --quiet "${VERSION}^{commit}" > /dev/null; then
    echo "ERROR: Git version not found: $VERSION"
    exit 1
fi

# Get the full commit ID
COMMIT=$(git rev-parse "${VERSION}^{commit}")

echo "Current Git branch:"
git branch --show-current

echo "Selected deployment version:"
echo "$COMMIT"

echo "Deployment directories:"
echo "Massive API: $MASSIVE_DIR"
echo "Email worker: $EMAIL_DIR"
echo "Backups: $BACKUP_DIR"

echo "Initial deployment checks completed."

STAGING_DIR=$(mktemp -d)

# Remove temporary files when the script exits
trap 'rm -rf "$STAGING_DIR"' EXIT

echo ""
echo "Preparing Git version for deloyment..."

# Extract the selected version into the temporary directory
git archive "$COMMIT" \
    dmz/massive_worker.php \
    dmz/rabbitmq_worker.php \
    email-worker/email_worker.py \
    | tar -x -C "$STAGING_DIR"

echo "Deployment files extracted successfully."
echo "Temporary directory: $STAGING_DIR"

# Verify that all requried files were extracted
for FILE in \
    dmz/massive_worker.php \
    dmz/rabbitmq_worker.php \
    email-worker/email_worker.py
do
    if [ ! -f "$STAGING_DIR/$FILE" ]; then
	echo "ERROR: Missing deployment file: $FILE"
	exit 1
    fi
done

echo "All required deployment files found."

# Validate application files before deployment
echo ""
echo "Checking application syntax..."

#Checking syntax
php -l "$STAGING_DIR/dmz/massive_worker.php"
php -l "$STAGING_DIR/dmz/rabbitmq_worker.php"

python3 - "$STAGING_DIR/email-worker/email_worker.py" <<'PY'
import ast
import sys
from pathlib import Path

file = Path(sys.argv[1])
ast.parse(file.read_text())

print("Python email worker syntax passed.")
PY

echo "All application syntax checks passed."

# Stop here if dry-run mode is enabled
if [ "$DRY_RUN" = true ]; then
    echo ""
    echo "DRY RUN: Deployment validation successful."
    echo "Selected commit: $COMMIT"
    echo ""
    echo "Files that would be deployed:"
    echo "$MASSIVE_DIR/massive_worker.php"
    echo "$MASSIVE_DIR/rabbitmq_worker.php"
    echo "$EMAIL_DIR/email_worker.py"
    echo ""
    echo "No apllication files were changed."
    exit 0
fi

# Create a timestamp for this backup
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

sudo mkdir -p -- "$BACKUP_DIR"

# Create the full backups path
CURRENT_BACKUP=$(sudo mktemp -d "$BACKUP_DIR/${TIMESTAMP}_XXXXXX")

echo ""
echo "Creating deployment backup..."

# Back up the current deployed Massive API Worker and Email Worker
sudo cp -a "$MASSIVE_DIR/massive_worker.php" "$CURRENT_BACKUP/"
sudo cp -a "$MASSIVE_DIR/rabbitmq_worker.php" "$CURRENT_BACKUP/"
sudo cp -a "$EMAIL_DIR/email_worker.py" "$CURRENT_BACKUP/"

#Verify that the backup files exist
for FILE in massive_worker.php rabbitmq_worker.php email_worker.py
do
    if ! sudo test -f "$CURRENT_BACKUP/$FILE"; then
	echo "ERROR: Backup is incomplete. Missing: $FILE"
	exit 1
    fi
done

echo "Backup created successfully!"
echo "Backup location: $CURRENT_BACKUP"
