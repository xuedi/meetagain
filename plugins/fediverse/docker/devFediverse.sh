#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../.."

COMPOSE=(docker-compose --env-file docker/services.env -p ma-fediverse -f plugins/fediverse/docker/compose.yml)
NETWORK=ma-fediverse
APP_CONTAINERS=(ma-php ma-worker ma-scheduler)
APP_ALIASES=(meetagain.local weiqi.meetagain.local)
CA=docker/certs/rootCA.pem
BUNDLE=/etc/ssl/certs/ca-certificates.crt
PASSWORD=interop-test-password

mastodon() {
    "${COMPOSE[@]}" exec -T fedi-mastodon-web "$@"
}

wait_for() {
    local label=$1
    shift
    for _ in $(seq 1 90); do
        if "$@" >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    echo "$label did not come up" >&2
    exit 1
}

join_app_containers() {
    for container in "${APP_CONTAINERS[@]}"; do
        if ! docker inspect -f '{{json .NetworkSettings.Networks}}' "$container" | grep -q "\"$NETWORK\""; then
            local aliases=()
            if [ "$container" = ma-php ]; then
                for alias in "${APP_ALIASES[@]}"; do aliases+=(--alias "$alias"); done
            fi
            docker network connect "${aliases[@]}" "$NETWORK" "$container"
        fi
        if ! docker exec "$container" grep -qF "$(sed -n 2p "$CA")" "$BUNDLE"; then
            docker exec -i -u root "$container" sh -c "cat >> $BUNDLE" < "$CA"
        fi
    done
}

trust_in_mobilizon() {
    local appended
    appended=$(docker exec -u root ma-fedi-mobilizon sh -c '
        for bundle in /lib/castore-*/priv/cacerts.pem /lib/certifi-*/priv/cacerts.pem; do
            grep -qF "$(sed -n 2p /ca/rootCA.pem)" "$bundle" || { cat /ca/rootCA.pem >> "$bundle"; echo yes; }
        done')
    if [ -n "$appended" ]; then
        docker restart ma-fedi-mobilizon >/dev/null
        wait_for "Mobilizon" "${COMPOSE[@]}" exec -T fedi-mobilizon /bin/mobilizon pid
    fi
}

up() {
    command -v mkcert >/dev/null || { echo "mkcert is not installed - see docker/certs/README.md" >&2; exit 1; }
    cp "$(mkcert -CAROOT)/rootCA.pem" "$CA"

    "${COMPOSE[@]}" up -d fedi-mastodon-db fedi-mastodon-redis fedi-mobilizon-db
    wait_for "Mastodon's database" "${COMPOSE[@]}" exec -T fedi-mastodon-db pg_isready -U mastodon
    "${COMPOSE[@]}" run --rm -T fedi-mastodon-web bundle exec rails db:prepare
    "${COMPOSE[@]}" up -d
    join_app_containers

    mastodon bin/tootctl accounts create alice --email alice@mastodon.meetagain.local --confirmed --role Owner >/dev/null 2>&1 || true
    mastodon bin/tootctl accounts approve alice >/dev/null 2>&1 || true
    local token
    token=$(mastodon bin/rails runner '
        app = Doorkeeper::Application.find_or_create_by!(name: "interop") { |a| a.redirect_uri = "urn:ietf:wg:oauth:2.0:oob"; a.scopes = "read write follow" }
        user = Account.find_local("alice").user
        token = Doorkeeper::AccessToken.where(application: app, resource_owner_id: user.id, revoked_at: nil).first ||
            Doorkeeper::AccessToken.create!(application: app, resource_owner_id: user.id, scopes: "read write follow")
        print token.token' | tail -1)

    wait_for "Mobilizon" "${COMPOSE[@]}" exec -T fedi-mobilizon /bin/mobilizon pid
    trust_in_mobilizon
    "${COMPOSE[@]}" exec -T fedi-mobilizon /bin/mobilizon_ctl users.new alice@mobilizon.meetagain.local \
        --admin --password "$PASSWORD" --profile-username alice --profile-display-name Alice >/dev/null 2>&1 || true

    echo "Mastodon:  https://mastodon.meetagain.local - user alice, API token $token"
    echo "Mobilizon: https://mobilizon.meetagain.local - alice@mobilizon.meetagain.local / $PASSWORD"
}

down() {
    for container in "${APP_CONTAINERS[@]}"; do
        docker network disconnect "$NETWORK" "$container" >/dev/null 2>&1 || true
    done
    "${COMPOSE[@]}" down "$@"
}

case "${1:-up}" in
    up) up ;;
    join) join_app_containers ;;
    down) down ;;
    reset) down -v ;;
    *) echo "usage: $0 up|join|down|reset" >&2; exit 1 ;;
esac
