FROM php:8.3-cli-alpine

# Security: Run as non-root
RUN addgroup -g 1000 sandbox && \
    adduser -u 1000 -G sandbox -s /bin/sh -D sandbox

# Install only necessary extensions
RUN apk add --no-cache \
    libzip-dev \
    && docker-php-ext-install \
    opcache \
    zip

# Disable dangerous functions
RUN echo "disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source" >> /usr/local/etc/php/conf.d/sandbox.ini

# Memory and execution limits
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/sandbox.ini && \
    echo "max_execution_time = 60" >> /usr/local/etc/php/conf.d/sandbox.ini && \
    echo "max_input_time = 60" >> /usr/local/etc/php/conf.d/sandbox.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/sandbox.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/sandbox.ini

WORKDIR /app
USER sandbox

ENTRYPOINT ["php"]