#!/bin/sh
set -eu

SMTP_MODE="${SMTP_MODE:-mailpit}"
SMTP_PORT="${SMTP_PORT:-1025}"
SMTP_FROM="${SMTP_FROM:-no-reply@localhost}"
SMTP_USER="${SMTP_USER:-}"
SMTP_PASS="${SMTP_PASS:-}"
SMTP_TLS="${SMTP_TLS:-off}"
SMTP_STARTTLS="${SMTP_STARTTLS:-off}"
SMTP_AUTH="${SMTP_AUTH:-off}"

if [ "$SMTP_MODE" = "mailpit" ]; then
    SMTP_HOST="${SMTP_HOST:-mailpit}"
    SMTP_PORT="${SMTP_PORT:-1025}"
    SMTP_TLS="off"
    SMTP_STARTTLS="off"
    SMTP_AUTH="off"
    SMTP_USER=""
    SMTP_PASS=""
else
    SMTP_HOST="${SMTP_HOST:-}"
    if [ -z "$SMTP_HOST" ]; then
        echo "SMTP_HOST is required when SMTP_MODE is not 'mailpit'" >&2
        exit 1
    fi

    SMTP_PORT="${SMTP_PORT:-587}"
    SMTP_AUTH="${SMTP_AUTH:-on}"
    SMTP_TLS="${SMTP_TLS:-on}"
    SMTP_STARTTLS="${SMTP_STARTTLS:-on}"
fi

sed \
    -e "s|__SMTP_HOST__|${SMTP_HOST}|g" \
    -e "s|__SMTP_PORT__|${SMTP_PORT}|g" \
    -e "s|__SMTP_AUTH__|${SMTP_AUTH}|g" \
    -e "s|__SMTP_TLS__|${SMTP_TLS}|g" \
    -e "s|__SMTP_STARTTLS__|${SMTP_STARTTLS}|g" \
    -e "s|__SMTP_FROM__|${SMTP_FROM}|g" \
    -e "s|__SMTP_USER__|${SMTP_USER}|g" \
    -e "s|__SMTP_PASS__|${SMTP_PASS}|g" \
    /opt/docker/msmtprc.template > /etc/msmtprc

chmod 644 /etc/msmtprc

exec docker-php-entrypoint "$@"
