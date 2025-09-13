#!/bin/sh
# Exit on error
set -e

# Start the cron service in the background.
cron

echo "Cron service started."

# Execute the original command (apache2-foreground)
exec "$@"
