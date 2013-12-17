# sag-php-tests
#
# Create a machine with CouchDB 1.4, PHP 5.*, phpunit, etc. You then include
# the version of Sag you want to test when invoking Docker.

FROM sbisbee/couchdb:1.4
MAINTAINER Sam Bisbee <sam@sbisbee.com>

# PHP
RUN apt-get install -y php5-cli php5-xdebug php5-curl

# Composer
RUN apt-get install -y curl
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

# PHPUnit
RUN composer global require 'phpunit/phpunit=3.7.*'
RUN ln -s ~/.composer/vendor/bin/phpunit /usr/local/bin/phpunit

# Sag
RUN mkdir /root/sag
ADD ./sag.tar /root/sag/

# Prep for the tests
RUN mkdir /tmp/sag
RUN echo "[admins]\nadmin = passwd" > /usr/local/etc/couchdb/local.d/sag.ini
ADD ./run.sh /root/run.sh

# Run the tests
ENTRYPOINT sh /root/run.sh /root/sag
