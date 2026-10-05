FROM uselagoon/redis-8

#######################################################
# Finalize Environment
#######################################################

# Horizon runs nicely with multiple databases
ENV DATABASES=5
