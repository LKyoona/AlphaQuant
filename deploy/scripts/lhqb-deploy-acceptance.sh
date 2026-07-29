#!/usr/bin/env bash
set -Eeuo pipefail

known_current="/data/lhqb/releases/20260727-175418-php81-check"
failed_candidate="/data/lhqb/releases/20260727-114414"
archive="/tmp/lhqb-release-validation.tar.gz"

test "$(readlink -f /data/lhqb/current)" = "${known_current}"
test ! -L "${failed_candidate}"
rm -rf -- "${failed_candidate}"

tar -C "${known_current}" -czf "${archive}" .
/usr/local/sbin/lhqb-deploy "${archive}"
/usr/local/sbin/lhqb-health-check
/usr/local/sbin/lhqb-rollback
/usr/local/sbin/lhqb-health-check

test "$(readlink -f /data/lhqb/current)" = "${known_current}"
rm -f -- "${archive}"

echo 'DEPLOY_AND_ROLLBACK_ACCEPTANCE=PASS'
echo "CURRENT_RELEASE=$(readlink -f /data/lhqb/current)"
echo "PREVIOUS_RELEASE=$(readlink -f /data/lhqb/previous)"
