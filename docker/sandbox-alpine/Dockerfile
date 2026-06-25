FROM alpine:latest

# Security: Run as non-root, minimal tools only
RUN addgroup -g 1000 sandbox && \
    adduser -u 1000 -G sandbox -s /bin/sh -D sandbox && \
    apk add --no-cache \
    busybox \
    coreutils

# Remove dangerous binaries
RUN rm -f /bin/ping /bin/ping6 /usr/bin/wget /usr/bin/curl /usr/bin/nc /bin/nc

WORKDIR /app
USER sandbox

ENTRYPOINT ["sh"]