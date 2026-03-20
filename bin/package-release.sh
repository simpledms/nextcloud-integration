#!/usr/bin/env bash

set -euo pipefail

APP_ID="simpledms_integration"
PROJECT_DIR="$(pwd)"
APP_DIR="${PROJECT_DIR}/${APP_ID}"
KEY_PATH="${HOME}/.nextcloud/certificates/${APP_ID}.key"
DIST_DIR="${PROJECT_DIR}/dist"

usage() {
  printf 'Usage: %s <version>\n' "$(basename "$0")"
  printf 'Example: %s 1.2.3\n' "$(basename "$0")"
}

if [[ $# -ne 1 ]]; then
  usage
  exit 1
fi

VERSION="$1"
if [[ ! "${VERSION}" =~ ^[0-9A-Za-z._-]+$ ]]; then
  printf 'Error: invalid version "%s"\n' "${VERSION}" >&2
  exit 1
fi

if [[ ! -d "${APP_DIR}" ]]; then
  printf 'Error: app directory not found: %s\n' "${APP_DIR}" >&2
  exit 1
fi

if [[ ! -f "${KEY_PATH}" ]]; then
  printf 'Error: private key not found: %s\n' "${KEY_PATH}" >&2
  exit 1
fi

if ! command -v tar >/dev/null 2>&1; then
  printf 'Error: tar command not available\n' >&2
  exit 1
fi

if ! command -v openssl >/dev/null 2>&1; then
  printf 'Error: openssl command not available\n' >&2
  exit 1
fi

mkdir -p "${DIST_DIR}"

ARCHIVE_PATH="${DIST_DIR}/${APP_ID}-${VERSION}.tar.gz"
SIGNATURE_PATH="${ARCHIVE_PATH}.sig"

tar -czf "${ARCHIVE_PATH}" -C "${PROJECT_DIR}" "${APP_ID}"

openssl dgst -sha512 -sign "${KEY_PATH}" "${ARCHIVE_PATH}" | openssl base64 > "${SIGNATURE_PATH}"

printf 'Created archive: %s\n' "${ARCHIVE_PATH}"
printf 'Created signature: %s\n' "${SIGNATURE_PATH}"
