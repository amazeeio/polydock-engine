FROM uselagoon/redis-7-persistent:25.11.0

#######################################################
# Finalize Environment
#######################################################

# Horizon runs nicely with multiple databases
ENV DATABASES=5
