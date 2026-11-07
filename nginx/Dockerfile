FROM nginx:latest@sha256:placeholder

# NOTE: replace `sha256:placeholder` with the real digest from
# `docker pull nginx:latest --quiet` before first build (Phase 5/6 pinning
# standard). Docker is unavailable on this host.

COPY ./nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

COPY ./app/public /var/www/html/public
