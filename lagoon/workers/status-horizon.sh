#!/bin/sh

echo "Loading Lagoon environment"

# Loading environment variables from .env and friends
source /lagoon/entrypoints/50-dotenv.sh

# Generate some additional enviornment variables
source /lagoon/entrypoints/55-generate-env.sh

echo "Done loading Lagoon environment"

if [ -z "$POLYDOCK_SRE_HORIZON_HEARTBEAT" ]; then
  echo "[WARNING] - POLYDOCK_SRE_HORIZON_HEARTBEAT is not set"
fi

if [ -f "config/horizon.php" ]; then
  COUNT=`ps ax | grep horizon:work | grep -v grep | wc -l`

  if [ $COUNT -gt 0 ]; then
	  echo "[INFO] - Horizon is running"
    if [ ! -z "$POLYDOCK_SRE_HORIZON_HEARTBEAT" ]; then
      curl --max-time 10 -XGET $POLYDOCK_SRE_HORIZON_HEARTBEAT
      echo "--"
      echo "[INFO] - Horizon heartbeat sent"
    fi
  else
	  echo "[WARNING] - Horizon is not running"

    if [ ! -z "$POLYDOCK_SRE_SLACK_WEBHOOK_URL" ]; then
      RUN_CONTEXT=$SERVICE_NAME.$LAGOON_GIT_SAFE_BRANCH.$LAGOON_PROJECT
      curl --max-time 10 -X POST -H 'Content-type: application/json' --data '{"text":":rotating_light: ['$RUN_CONTEXT'] Horizon is NOT running - attempting supervisorctl restart"}' $POLYDOCK_SRE_SLACK_WEBHOOK_URL
    fi

    # Self-heal: kick the program if supervisord gave up on it (FATAL).
    # Requires the control socket configured in worker-supervisord.conf.
    supervisorctl -c /etc/supervisord.conf restart horizon || echo "[WARNING] - supervisorctl restart failed"
  fi
else
  echo "[WARNING] - Horizon is not installed";
  sleep 3
fi
