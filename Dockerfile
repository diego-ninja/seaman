FROM ubuntu:24.04

LABEL maintainer="Diego Rin Martín"

ARG WWWGROUP
ARG NODE_VERSION=20

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y software-properties-common gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev dnsutils librsvg2-bin fswatch ffmpeg nano fish\
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get install -y python-is-python3 php-common php8.4-common \
        php8.4-cli php8.4-dev \
        php8.4-pgsql php8.4-sqlite3 php8.4-gd \
        php8.4-curl \
        php8.4-imap php8.4-mysql php8.4-mbstring \
        php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
        php8.4-intl php8.4-readline \
        php8.4-ldap \
        php8.4-msgpack php8.4-igbinary php8.4-redis \
        php8.4-memcached php8.4-pcov php8.4-imagick php8.4-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && npm install -g pnpm \
    && npm install -g bun \
    && apt-get update \
    && apt-get install -y \
        php8.4-yaml \
        php8.4-gmp \
        php8.4-maxminddb \
        php-pear \
        jq \
        libbrotli-dev \
        libpcre3-dev \
        libssl-dev \
        pkg-config \
        protobuf-compiler librdkafka-dev \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.4

RUN groupadd --force -g $WWWGROUP seaman
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 seaman

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
&& mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

# Make xdebug-toggle script executable
COPY scripts/xdebug-toggle.sh /usr/local/bin/xdebug-toggle
RUN chmod +x /usr/local/bin/xdebug-toggle || true

EXPOSE 8000
CMD ["symfony", "server:start", "--port=8000"]