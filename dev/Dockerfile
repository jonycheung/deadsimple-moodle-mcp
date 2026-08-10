# Development image for local_simplemcp.
#
# moodlehq/moodle-php-apache is the image Moodle HQ's own CI uses, so the PHP
# build here matches what the plugin will be reviewed and tested against. It
# already carries every extension Moodle requires plus Xdebug.
ARG PHP_VERSION=8.1
FROM moodlehq/moodle-php-apache:${PHP_VERSION}

# git: fetching Moodle core at first boot. unzip/zip: plugin zip handling.
# postgresql-client: `make psql`. rsync: merging core over the bind-mounted plugin.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip zip rsync postgresql-client jq \
    && rm -rf /var/lib/apt/lists/*

# moodle-plugin-ci gives `make check` the exact phpcs/phpmd/phpdoc rules the
# GitHub Actions workflow runs, so local results and CI results agree.
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer create-project -n --no-dev --prefer-dist \
        moodlehq/moodle-plugin-ci /opt/moodle-plugin-ci ^4 \
    && ln -s /opt/moodle-plugin-ci/bin/moodle-plugin-ci /usr/local/bin/moodle-plugin-ci

# Moodle's CLI scripts are long-running; the web SAPI limits do not apply but
# the CLI ini still needs headroom for phpunit and behat.
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 0'; \
        echo 'max_input_vars = 5000'; \
    } > /usr/local/etc/php/conf.d/99-moodle-dev.ini

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
