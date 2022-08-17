FROM alpine:3.8

RUN apk --update --no-cache add \
  curl \
  php5 \
  php5-cli \
  php5-openssl \
  php5-json \
  php5-phar \
  php5-curl \
  php5-dom

RUN ln -s /usr/bin/php5 /usr/bin/php

RUN curl -s https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

RUN apk --update --no-cache add \
  build-base \
  python3-dev \
  python3 \
  musl \
  musl-utils \
  shared-mime-info \
  fontconfig \
  gdk-pixbuf-dev \
  pango-dev \
  cairo-dev \
  libffi-dev \
  libjpeg-turbo-dev \
  ttf-freefont \
  pdfgrep

RUN curl https://bootstrap.pypa.io/pip/3.6/get-pip.py | python3
RUN pip install weasyprint==52.5
RUN fc-cache -fv && fc-list | sort

WORKDIR /code
COPY . .
RUN composer install \
  --no-interaction \
  --prefer-dist

WORKDIR /code/tests/Integration
RUN composer install \
  --no-interaction

WORKDIR /code

CMD composer run-script unit-tests \
  && php tests/Integration/generate-pdf.php
