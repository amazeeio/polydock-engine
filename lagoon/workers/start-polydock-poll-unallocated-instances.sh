#!/bin/sh
echo "Loading Lagoon environment"

# Loading environment variables from .env and friends
source /lagoon/entrypoints/50-dotenv.sh

# Generate some additional enviornment variables
source /lagoon/entrypoints/55-generate-env.sh

echo "Done loading Lagoon environment"

if [ -f "/app/artisan" ]; then
  /usr/local/bin/php artisan polydock:poll-unallocated-instances
  sleep 5
else
  echo "Laravel is not installed";
  sleep 10
fi
