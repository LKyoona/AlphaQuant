#!/usr/bin/env bash
set -Eeuo pipefail

if [ -f /etc/lhqb/deploy.env ]; then
  # shellcheck disable=SC1091
  source /etc/lhqb/deploy.env
fi

APP_ROOT="/data/lhqb"
DOMAIN="${LHQB_DOMAIN:-neuranet.site}"
CURRENT_LINK="${APP_ROOT}/current"
PREVIOUS_LINK="${APP_ROOT}/previous"
current_release="$(readlink -f "${CURRENT_LINK}")"
previous_release="$(readlink -f "${PREVIOUS_LINK}" 2>/dev/null || true)"

if [ -z "${previous_release}" ] || [ ! -d "${previous_release}" ]; then
  echo 'No valid previous release is available.' >&2
  exit 2
fi

ln -sfn "${previous_release}" "${CURRENT_LINK}.next"
mv -Tf "${CURRENT_LINK}.next" "${CURRENT_LINK}"
ln -sfn "${current_release}" "${PREVIOUS_LINK}"

if ! nginx -t || ! systemctl reload php8.1-fpm || ! systemctl reload nginx || \
   ! systemctl restart lhqb-market.service || \
   ! curl --silent --show-error --fail --max-time 20 \
      --resolve "${DOMAIN}:443:127.0.0.1" \
      "https://${DOMAIN}/api/home/main/init" >/dev/null; then
  ln -sfn "${current_release}" "${CURRENT_LINK}.next"
  mv -Tf "${CURRENT_LINK}.next" "${CURRENT_LINK}"
  ln -sfn "${previous_release}" "${PREVIOUS_LINK}"
  systemctl reload php8.1-fpm || true
  systemctl reload nginx || true
  systemctl restart lhqb-market.service || true
  echo 'Rollback validation failed; current release was restored.' >&2
  exit 3
fi

echo "ROLLBACK=PASS"
echo "CURRENT_RELEASE=${previous_release}"
echo "PREVIOUS_RELEASE=${current_release}"
