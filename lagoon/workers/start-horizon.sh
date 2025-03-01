#!/bin/sh

echo "Loading Lagoon environment"

# Loading environment variables from .env and friends
source /lagoon/entrypoints/50-dotenv.sh

# Generate some additional enviornment variables
source /lagoon/entrypoints/55-generate-env.sh

echo "Done loading Lagoon environment"

if [ -f "/app/config/horizon.php" ]; then
  
  if [ ! -z "$POLYDOCK_SRE_SLACK_WEBHOOK_URL" ]; then
    RUN_CONTEXT=$SERVICE_NAME.$LAGOON_GIT_SAFE_BRANCH.$LAGOON_PROJECT

    echo "[$SERVICE_NAME] Sending Slack notification"
    curl -X POST -H 'Content-type: application/json' --data '{"text":":rocket: ['$RUN_CONTEXT'] Horizon Started"}' $POLYDOCK_SRE_SLACK_WEBHOOK_URL
  fi

  /usr/local/bin/php artisan horizon
else
  echo "Horizon is not installed";
  sleep 10
fi
