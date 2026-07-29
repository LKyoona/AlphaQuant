#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="/data/lhqb"
BACKUP_ROOT="${APP_ROOT}/backups/automatic"
DATABASE_DIR="${BACKUP_ROOT}/database"
FILES_DIR="${BACKUP_ROOT}/files"
LOG_FILE="${APP_ROOT}/logs/application/backup.log"
DATABASE_NAME="lhqb"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DATABASE_ARCHIVE="${DATABASE_DIR}/${DATABASE_NAME}-${STAMP}.sql.gz"
FILES_ARCHIVE="${FILES_DIR}/shared-${STAMP}.tar.gz"

mkdir -p "${DATABASE_DIR}" "${FILES_DIR}" "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}"
chmod 600 "${LOG_FILE}"

exec >>"${LOG_FILE}" 2>&1

echo "[$(date --iso-8601=seconds)] backup started"

mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --default-character-set=utf8mb4 \
  "${DATABASE_NAME}" | gzip -9 >"${DATABASE_ARCHIVE}"

gzip -t "${DATABASE_ARCHIVE}"
test -s "${DATABASE_ARCHIVE}"

tar -C "${APP_ROOT}" -czf "${FILES_ARCHIVE}" \
  shared/data/conf \
  shared/public/upload \
  shared/python/news-crawler.env

tar -tzf "${FILES_ARCHIVE}" >/dev/null
test -s "${FILES_ARCHIVE}"

chmod 600 "${DATABASE_ARCHIVE}" "${FILES_ARCHIVE}"

find "${DATABASE_DIR}" -type f -name '*.sql.gz' -mtime "+${RETENTION_DAYS}" -delete
find "${FILES_DIR}" -type f -name '*.tar.gz' -mtime "+${RETENTION_DAYS}" -delete

echo "[$(date --iso-8601=seconds)] backup completed database=$(basename "${DATABASE_ARCHIVE}") files=$(basename "${FILES_ARCHIVE}")"
