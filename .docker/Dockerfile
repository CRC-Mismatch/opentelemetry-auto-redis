FROM evinobr/api-platform-php-cli:php-8.3-opentelemetry
LABEL maintainer="Evino IT <it@evino.com.br>"
LABEL description="Evino CLI concord image"
ARG APP_HOMEDIR=/home/app
ENV APP_HOMEDIR=$APP_HOMEDIR
ARG PROJECT_PATH=/var/www/html
ENV PROJECT_PATH=${PROJECT_PATH}
ENV REDIS_HOSTNAME=redis
ENV REDIS_PORT=6379
ENV PHP_CLI_MEMORY_LIMIT=256M
ENV PHP_MEMORY_LIMIT=256M
ENV TERM=$TERM
WORKDIR $PROJECT_PATH
ARG USERID=1000
ARG GROUPID=1000
RUN set -e; \
    $APP_HOMEDIR/scripts/init-app-user.sh; \
    docker-php-ext-enable xdebug pcov;
COPY config/php/development.ini /usr/local/etc/php/conf.d/

RUN set -e ; \
    apt-get update ; \
    apt-get install -y --no-install-recommends sudo golang-chroma command-not-found ; \
    adduser app sudo ; \
    echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

USER app

ARG USER_SHELL=/bin/bash
ARG THEME="maran"
RUN [ "${USER_SHELL#*"/zsh"}" != "$USER_SHELL" ] && { \
    [ "$THEME" = "powerlevel10k/powerlevel10k" ] && export THEME=default || [ -z "$THEME" ] && export THEME=maran; \
    sh -c "$(wget -O- https://github.com/deluan/zsh-in-docker/releases/download/v1.1.5/zsh-in-docker.sh)" -- -p debian -p sudo -p command-not-found -p colorize -p symfony2 -p composer -p npm -p yarn -t "$THEME" -a "setopt no_nomatch" && \
    sudo chsh -s /usr/bin/zsh app ; \
    } || echo "Using bash"

ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php", "-a"]
