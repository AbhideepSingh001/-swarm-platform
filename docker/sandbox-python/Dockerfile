FROM python:3.12-slim

# Security: Run as non-root
RUN groupadd -r sandbox && useradd -r -g sandbox sandbox

# No network tools, no compilers
RUN apt-get update && apt-get install -y --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Restrict pip (no external installs)
ENV PIP_NO_INDEX=1
ENV PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app
USER sandbox

ENTRYPOINT ["python3"]