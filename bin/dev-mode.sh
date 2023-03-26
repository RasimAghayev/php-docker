#!/bin/sh
# XDEBUG_MODE=debug
docker compose -f docker-compose.yaml \
        -f docker-compose.dev.yaml \
        --env-file=.env.local up "$@" #-d --build