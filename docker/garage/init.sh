#!/bin/sh
# Prepares the dev Garage: a layout for its single node, the app's key and its three buckets. Safe to run again.
set -eu

KEY_ID="GK6d656574616761696e646576"
KEY_SECRET="4469dab6ad31edf40ec5e2dafc7f9aec9c6e0a1a4394bf42d557483570d2b4b3"
PREFIX="meetagain"

garage() {
    $DOCKER exec -T -e RUST_LOG=warn garage /garage "$@"
}

tries=0
until garage status >/dev/null 2>&1; do
    tries=$((tries + 1))
    [ "$tries" -lt 30 ] || { echo "Garage did not come up" >&2; exit 1; }
    sleep 1
done

if garage layout show 2>/dev/null | grep -q "No nodes currently have a role"; then
    node="$(garage node id -q | cut -d@ -f1)"
    garage layout assign -z dev -c 1G "$node" >/dev/null
    garage layout apply --version 1 >/dev/null
fi

if ! garage key info "$KEY_ID" >/dev/null 2>&1; then
    garage key import --yes -n "$PREFIX" "$KEY_ID" "$KEY_SECRET" >/dev/null
fi

for bucket in images thumbnails private; do
    if ! garage bucket info "$PREFIX-$bucket" >/dev/null 2>&1; then
        garage bucket create "$PREFIX-$bucket" >/dev/null
    fi
    garage bucket allow --read --write --owner "$PREFIX-$bucket" --key "$KEY_ID" >/dev/null
done

garage bucket website --allow "$PREFIX-thumbnails" >/dev/null
