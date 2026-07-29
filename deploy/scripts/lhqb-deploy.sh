#!/usr/bin/env bash
set -Eeuo pipefail

if [ -f /etc/lhqb/deploy.env ]; then
  # shellcheck disable=SC1091
  source /etc/lhqb/deploy.env
fi

APP_ROOT="/data/lhqb"
DOMAIN="${LHQB_DOMAIN:-neuranet.site}"
RELEASES_DIR="${APP_ROOT}/releases"
CURRENT_LINK="${APP_ROOT}/current"
PREVIOUS_LINK="${APP_ROOT}/previous"
ARCHIVE="${1:-}"

if [ -z "${ARCHIVE}" ] || [ ! -f "${ARCHIVE}" ]; then
  echo "Usage: lhqb-deploy /absolute/path/lhqb-release.tar.gz" >&2
  exit 2
fi

stamp="$(date +%Y%m%d-%H%M%S)"
release_dir="${RELEASES_DIR}/${stamp}"
old_release="$(readlink -f "${CURRENT_LINK}")"

cleanup_failed_release() {
  if [ -d "${release_dir}" ] && [ "$(readlink -f "${CURRENT_LINK}" 2>/dev/null || true)" != "${release_dir}" ]; then
    rm -rf -- "${release_dir}"
  fi
}
trap cleanup_failed_release ERR

mkdir -p "${release_dir}"
tar -xzf "${ARCHIVE}" -C "${release_dir}"

if [ ! -f "${release_dir}/public/index.php" ] || [ ! -f "${release_dir}/public/api/index.php" ]; then
  echo 'Invalid release: public/index.php or public/api/index.php is missing.' >&2
  rm -rf -- "${release_dir}"
  exit 3
fi

rm -rf -- "${release_dir}/data/conf" "${release_dir}/data/runtime" "${release_dir}/public/upload"
mkdir -p "${release_dir}/data" "${release_dir}/public"
ln -s "${APP_ROOT}/shared/data/conf" "${release_dir}/data/conf"
ln -s "${APP_ROOT}/shared/data/runtime" "${release_dir}/data/runtime"
ln -s "${APP_ROOT}/shared/public/upload" "${release_dir}/public/upload"

while IFS= read -r -d '' php_file; do
  php -l "${php_file}" >/dev/null
done < <(find "${release_dir}/api" "${release_dir}/app" "${release_dir}/public" "${release_dir}/system" -type f -name '*.php' -print0)

chown -R root:www-data "${release_dir}"
find "${release_dir}" -type d -exec chmod 0755 {} +
find "${release_dir}" -type f -exec chmod 0644 {} +

/usr/local/sbin/lhqb-backup

ln -sfn "${old_release}" "${PREVIOUS_LINK}"
ln -sfn "${release_dir}" "${CURRENT_LINK}.next"
mv -Tf "${CURRENT_LINK}.next" "${CURRENT_LINK}"

if ! nginx -t || ! systemctl reload php8.1-fpm || ! systemctl reload nginx || \
   ! curl --silent --show-error --fail --max-time 20 \
      --resolve "${DOMAIN}:443:127.0.0.1" \
      "https://${DOMAIN}/api/home/main/init" >/dev/null; then
  ln -sfn "${old_release}" "${CURRENT_LINK}.next"
  mv -Tf "${CURRENT_LINK}.next" "${CURRENT_LINK}"
  systemctl reload php8.1-fpm || true
  systemctl reload nginx || true
  echo "Deployment failed and rolled back to ${old_release}." >&2
  exit 4
fi

trap - ERR
echo "DEPLOYMENT=PASS"
echo "CURRENT_RELEASE=${release_dir}"
echo "PREVIOUS_RELEASE=${old_release}"
