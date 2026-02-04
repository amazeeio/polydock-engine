ARG CLI_IMAGE
FROM ${CLI_IMAGE} 

#######################################################
# Replace the php.ini
#######################################################
# COPY lagoon/worker-php.ini /usr/local/etc/php/php.ini

#######################################################
# Install Supervisord
#######################################################
RUN apk add --update supervisor && rm  -rf /tmp/* /var/cache/apk/*
RUN mkdir -p /etc/supervisor/conf.d/ && fix-permissions /etc/supervisor/conf.d
ADD lagoon/worker-supervisord.conf /etc/supervisord.conf
ADD lagoon/worker-supervisord-horizon.conf /etc/supervisor/conf.d/
ADD lagoon/worker-supervisord-schedule.conf /etc/supervisor/conf.d/
ADD lagoon/worker-supervisord-polydock-poll-deployment-status.conf /etc/supervisor/conf.d/
ADD lagoon/worker-supervisord-polydock-poll-unallocated-instances.conf /etc/supervisor/conf.d/

#######################################################
# Run Supervisor
#######################################################
CMD ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]
