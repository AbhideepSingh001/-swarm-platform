#!/bin/bash
set -e

echo "Building sandbox Docker images..."

docker build -t sandbox-php:8.3 -f docker/sandbox-php/Dockerfile docker/sandbox-php/
docker build -t sandbox-python:3.12 -f docker/sandbox-python/Dockerfile docker/sandbox-python/
docker build -t sandbox-node:20 -f docker/sandbox-node/Dockerfile docker/sandbox-node/
docker build -t sandbox-alpine:latest -f docker/sandbox-alpine/Dockerfile docker/sandbox-alpine/

echo "All sandbox images built successfully."
echo ""
echo "Verify with: docker images | grep sandbox"