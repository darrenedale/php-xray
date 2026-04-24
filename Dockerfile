ARG PHP_VERSION=8.3
FROM darrenedale/equit:php-${PHP_VERSION}-cli
RUN printf "xray\nxray\n" | adduser -u 1000 -h /xray xray
