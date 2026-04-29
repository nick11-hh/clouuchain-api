FROM php:8.2-cli

ENV TZ=Asia/Shanghai

RUN apt-get update && apt-get install -y -f --allow-downgrades \
	sudo \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    git \
    zip \
    zlib1g-dev \
    supervisor \
	net-tools \
    procps \
	net-tools \
	curl \
	wget \
	libzip-dev \
    wkhtmltopdf \
    ghostscript

RUN  docker-php-ext-install -j$(nproc) iconv mysqli pdo_mysql zip pcntl bcmath

RUN  docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install -j$(nproc) gd

RUN pecl install -o -f mcrypt && \
docker-php-ext-enable mcrypt

RUN pecl install -o -f redis \
&&  rm -rf /tmp/pear \
&&  docker-php-ext-enable redis

RUN pecl install -o -f xlswriter \
&&  docker-php-ext-enable xlswriter

RUN rm -rf /tmp/pear

#安装PDF字体
RUN mkdir -p /usr/share/fonts/chinese/TrueType/ \
&& wget https://github.com/yunliantiao/docker_build_resource/raw/master/simsun.ttc -O /usr/share/fonts/chinese/TrueType/simsun.ttc \
&& wget https://github.com/yunliantiao/docker_build_resource/raw/master/FZHTJW.TTF  -O /usr/share/fonts/truetype/FZHTJW.TTF

#安装comoser
RUN wget https://mirrors.aliyun.com/composer/composer.phar -O /usr/bin/composer
RUN chmod +x /usr/bin/composer

COPY ./php.ini /usr/local/etc/php/conf.d/zz-php.ini
COPY ./supervisor_conf.d /etc/supervisor/conf.d

WORKDIR /var/www/html

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
