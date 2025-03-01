#!/bin/sh

echo "Loading Lagoon environment"

# Loading environment variables from .env and friends
source /lagoon/entrypoints/50-dotenv.sh

# Generate some additional enviornment variables
source /lagoon/entrypoints/55-generate-env.sh

echo "Done loading Lagoon environment"

# LAGOON_ENVIRONMENT_TYPE="TEST"

if [ -f "/app/config/horizon.php" ]; then
  
  if [ ! -z "$POLYDOCK_SRE_SLACK_WEBHOOK_URL" ]; then
    echo "[$SERVICE_NAME] Sending Slack notification"
    curl -X POST -H 'Content-type: application/json' --data '{"text":":rocket: [$SERVICE_NAME] Horizon Started"}' $POLYDOCK_SRE_SLACK_WEBHOOK_URL
  fi

  /usr/local/bin/php artisan horizon
else
  echo "Horizon is not installed";
  sleep 10
fi
