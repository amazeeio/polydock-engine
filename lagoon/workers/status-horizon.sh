#!/bin/sh

echo "Loading Lagoon environment"

# Loading environment variables from .env and friends
source /lagoon/entrypoints/50-dotenv.sh

# Generate some additional enviornment variables
source /lagoon/entrypoints/55-generate-env.sh

echo "Done loading Lagoon environment"

if [ -z "$POLYDOCK_SRE_HORIZON_HEARTBEAT" ]; then
  echo "[WARNING] - POLYDOCK_SRE_HORIZON_HEARTBEAT is not set"
  exit 0
fi

if [ -f "config/horizon.php" ]; then
  COUNT=`ps ax | grep horizon:work | grep -v grep | wc -l`

  if [ $COUNT -gt 0 ]; then
	  echo "[INFO] - Horizon is running"
    curl -XGET $POLYDOCK_SRE_HORIZON_HEARTBEAT
    echo "--"
    echo "[INFO] - Horizon heartbeat sent"
  else
	  echo "[WARNING] - Horizon is not running"
  fi
else
  echo "[WARNING] - Horizon is not installed";
  sleep 3
fi
