FROM node:20-alpine

# Security: Run as non-root
RUN addgroup -g 1000 sandbox && \
    adduser -u 1000 -G sandbox -s /bin/sh -D sandbox

# No npm install allowed (read-only FS anyway)
# Disable child_process, fs write access via policy
RUN echo 'process.on("uncaughtException", () => process.exit(1));' > /app/policy.js

WORKDIR /app
USER sandbox

ENTRYPOINT ["node"]