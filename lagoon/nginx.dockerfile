ARG CLI_IMAGE
FROM ${CLI_IMAGE} as cli
FROM uselagoon/nginx:25.11.0

#######################################################
# Customize Nginx to Laravel Land
#######################################################
COPY lagoon/nginx-laravel.conf /etc/nginx/conf.d/app.conf

#######################################################
# Copy the prebuild laravel app to the Nginx container
#######################################################
COPY --from=cli /app /app

#######################################################
# Finalize Environment
#######################################################
ENV WEBROOT=public
