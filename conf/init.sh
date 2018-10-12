#!/bin/sh

#IMPORTANT!: Note that we do envsubst for all env variables for env.template. Take care with '$' characters in env.template, strings that start with $ will be treated like env vars.

chown  www-data /var/log/
#rc-admin
envsubst < ./env.template > /var/www/rc-api/.env

#log_files
envsubst '${DEPLOYMENT_TYPE}' < ./log_files.yml.template > /etc/log_files.yml


/usr/bin/supervisord -c /etc/supervisord.conf
