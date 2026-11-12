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

echo "Current Git branch:"
git branch --show-current

echo "Current Git commit:"
git rev-parse --short HEAD

echo "Deployment directories:"
echo "Massive API: $MASSIVE_DIR"
echo "Email worker: $EMAIL_DIR"
echo "Backups: $BACKUP_DIR"

echo "Initial deployment checks completed."

# Create a timestamp for this backup
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Create the full backups path
CURRENT_BACKUP="$BACKUP_DIR/$TIMESTAMP"

echo ""
echo "Creating deployment backup..."

# Create a new backup directory
sudo mkdir -p "$CURRENT_BACKUP"

# Back up the current deployed Massive API Worker and Email Worker
sudo cp -a "$MASSIVE_DIR/massive_worker.php" "$CURRENT_BACKUP/"
sudo cp -a "$MASSIVE_DIR/rabbitmq_worker.php" "$CURRENT_BACKUP/"
sudo cp -a "$EMAIL_DIR/email_worker.py" "$CURRENT_BACKUP/"

echo "Backup created successfully!"
echo "backup location: $CURRENT_BACKUP"
