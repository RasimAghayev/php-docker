FROM nginx:latest@sha256:165ddde8751e75a1c907d24ef10d9f6deb08b57e0fe56caf67512486aff76d82

# NOTE: replace `sha256:placeholder` with the real digest from
# `docker pull nginx:latest --quiet` before first build (Phase 5/6 pinning
# standard). Docker is unavailable on this host.

COPY ./nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

COPY ./app/public /var/www/html/public
